#!/usr/bin/python
# coding=UTF-8

# ------------------------------------------------------------------------------
#	
#	RFXCMD.PY
#	
#	Copyright (C) 2012-2013 Sebastian Sjoholm, sebastian.sjoholm@gmail.com
#	
#	This program is free software: you can redistribute it and/or modify
#	it under the terms of the GNU General Public License as published by
#	the Free Software Foundation, either version 3 of the License, or
#	(at your option) any later version.
#	
#	This program is distributed in the hope that it will be useful,
#	but WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#	GNU General Public License for more details.
#	
#	You should have received a copy of the GNU General Public License
#	along with this program.  If not, see <http://www.gnu.org/licenses/>.
#	
#	Version history can be found at 
#	http://code.google.com/p/rfxcmd/wiki/VersionHistory
#
#	$Rev: 515 $
#	$Date: 2013-05-18 14:17:08 +0200 (Sat, 18 May 2013) $
#
#	NOTES
#	
#	RFXCOM is a Trademark of RFSmartLink.
#
# ------------------------------------------------------------------------------
#
#                          Protocol License Agreement                      
#                                                                    
# The RFXtrx protocols are owned by RFXCOM, and are protected under applicable
# copyright laws.
#
# ==============================================================================
# It is only allowed to use this protocol or any part of it for RFXCOM products
# ==============================================================================
#
# The above Protocol License Agreement and the permission notice shall be 
# included in all software using the RFXtrx protocols.
#
# Any use in violation of the foregoing restrictions may subject the user to 
# criminal sanctions under applicable laws, as well as to civil liability for 
# the breach of the terms and conditions of this license.
#
# ------------------------------------------------------------------------------

__author__ = "Sebastian Sjoholm"
__copyright__ = "Copyright 2012-2013, Sebastian Sjoholm"
__license__ = "GPL"
__version__ = "0.3 (" + filter(str.isdigit, "$Rev: 515 $") + ")"
__maintainer__ = "Sebastian Sjoholm"
__email__ = "sebastian.sjoholm@gmail.com"
__status__ = "Development"
__date__ = "$Date: 2013-05-18 14:17:08 +0200 (Sat, 18 May 2013) $"

# Default modules
import pdb
import string
import sys
import os
import time
import datetime
import binascii
import traceback
import subprocess
import re
import logging
import signal
import xml.dom.minidom as minidom
from optparse import OptionParser
import socket
import select

# RFXCMD modules
try:
	from lib.rfx_socket import *
except ImportError:
	print "Error: module lib/rfx_socket not found"
	sys.exit(1)

# RFXCMD modules
try:
	from lib.rfx_command import *
except ImportError:
	print "Error: module lib/rfx_command not found"
	sys.exit(1)

try:
	import lib.rfx_sensors
except ImportError:
	print "Error: module lib/rfx_sensors not found"
	sys.exit(1)

try:
	from lib.rfx_utils import *
except ImportError:
	print "Error: module lib/rfx_utils not found"
	sys.exit(1)

try:
	from lib.rfx_decode import *
except ImportError:
	print "Error: module lib/rfx_decode not found"
	sys.exit(1)

try:
	from lib import rfx_xplcom
except ImportError:
	print "Error: module lib/rfx_xplcom not found"
	pass

# 3rd party modules
# These might not be needed, depended on usage

# SQLite
try:
	import sqlite3
except ImportError:
	pass

# MySQL
try:
	import MySQLdb
except ImportError:
	pass
	
# Serial
try:
	import serial
except ImportError:
	pass

# ------------------------------------------------------------------------------
# VARIABLE CLASSS
# ------------------------------------------------------------------------------

class config_data:
	def __init__(
		self, 
		mysql_active = False,
		mysql_server = '',
		mysql_database = '',
		mysql_username = "",
		mysql_password = "",
		trigger_active = False,
		trigger_onematch = False,
		trigger_file = "",
		trigger_timeout = 10,
		sqlite_active = False,
		sqlite_database = "",
		sqlite_table = "",
		loglevel = "info",
		logfile = "rfxcmd.log",
		graphite_active = False,
		graphite_server = "",
		graphite_port = "",
		program_path = "",
		xpl_active = False,
		xpl_host = "",
		socketserver = False,
		sockethost = "",
		socketport = "",
		whitelist_active = False,
		whitelist_file = ""
		):
        
		self.mysql_active = mysql_active
		self.mysql_server = mysql_server
		self.mysql_database = mysql_database
		self.mysql_username = mysql_username
		self.mysql_password = mysql_password
		self.trigger_active = trigger_active
		self.trigger_onematch = trigger_onematch
		self.trigger_file = trigger_file
		self.trigger_timeout = trigger_timeout
		self.sqlite_active = sqlite_active
		self.sqlite_database = sqlite_database
		self.sqlite_table = sqlite_table
		self.loglevel = loglevel
		self.logfile = logfile
		self.graphite_active = graphite_active
		self.graphite_server = graphite_server
		self.graphite_port = graphite_port
		self.program_path = program_path
		self.xpl_active = xpl_active
		self.xpl_host = xpl_host
		self.socketserver = socketserver
		self.sockethost = sockethost
		self.socketport = socketport
		self.whitelist_active = whitelist_active
		self.whitelist_file = whitelist_file

class cmdarg_data:
	def __init__(
		self,
		configfile = "",
		action = "",
		rawcmd = "",
		device = "",
		createpid = False,
		pidfile = "",
		printout_complete = True,
		printout_csv = False
		):

		self.configfile = configfile
		self.action = action
		self.rawcmd = rawcmd
		self.device = device
		self.createpid = createpid
		self.pidfile = pidfile
		self.printout_complete = printout_complete
		self.printout_csv = printout_csv

class rfxcmd_data:
	def __init__(
		self,
		reset = "0d00000000000000000000000000",
		status = "0d00000002000000000000000000",
		save = "0d00000006000000000000000000"
		):

		self.reset = reset
		self.status = status
		self.save = save

class serial_data:
	def __init__(
		self,
		port = None,
		rate = 38400,
		timeout = 9
		):

		self.port = port
		self.rate = rate
		self.timeout = timeout

class trigger_data:
	def __init__(
		self,
		data = ""
		):

		self.data = data

class whitelist_data:
	def __init__(
		self,
		data = ""
		):

		self.data = data

# ----------------------------------------------------------------------------
# DEAMONIZE
# Credit: George Henze
# ----------------------------------------------------------------------------

def shutdown():
	# clean up PID file after us
	logger.debug("Shutdown")

	if cmdarg.createpid:
		logger.debug("Removing PID file " + str(cmdarg.pidfile))
		os.remove(cmdarg.pidfile)

	if serial_param.port is not None:
		logger.debug("Close serial port")
		serial_param.port.close()
		serial_param.port = None

	logger.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)
    
def handler(signum=None, frame=None):
	if type(signum) != type(None):
		logger.debug("Signal %i caught, exiting..." % int(signum))
		shutdown()

def daemonize():

	try:
		pid = os.fork()
		if pid != 0:
			sys.exit(0)
	except OSError, e:
		raise RuntimeError("1st fork failed: %s [%d]" % (e.strerror, e.errno))

	os.setsid() 

	prev = os.umask(0)
	os.umask(prev and int('077', 8))

	try:
		pid = os.fork() 
		if pid != 0:
			sys.exit(0)
	except OSError, e:
		raise RuntimeError("2nd fork failed: %s [%d]" % (e.strerror, e.errno))

	dev_null = file('/dev/null', 'r')
	os.dup2(dev_null.fileno(), sys.stdin.fileno())

	if cmdarg.createpid == True:
		pid = str(os.getpid())
		logger.debug("Writing PID " + pid + " to " + str(cmdarg.pidfile))
		file(cmdarg.pidfile, 'w').write("%s\n" % pid)

# ----------------------------------------------------------------------------

def send_graphite(CARBON_SERVER, CARBON_PORT, lines):
	"""
	Send data to graphite
	Credit: Frédéric Pégé
	"""	
	sock = None
	for res in socket.getaddrinfo(CARBON_SERVER,int(CARBON_PORT), socket.AF_UNSPEC, socket.SOCK_STREAM):
		af, socktype, proto, canonname, sa = res
		try:
			sock = socket.socket(af, socktype, proto)
		except socket.error as msg:
			sock = None
			continue
		try:
			sock.connect(sa)
		except socket.error as msg:
			sock.close()
			sock = None
			continue
		break

	if sock is None:
		print 'could not open socket'
		sys.exit(1)
	
	message = '\n'.join(lines) + '\n' #all lines must end in a newline
	sock.sendall(message)
	sock.close()

# ----------------------------------------------------------------------------

def readbytes(number):
	"""
	Read x amount of bytes from serial port. 
	Credit: Boris Smus http://smus.com
	"""
	buf = ''
	for i in range(number):
		try:
			byte = serial_param.port.read()
		except IOError, e:
			print "Error: %s" % e
		buf += byte

	return buf

# ----------------------------------------------------------------------------

def insert_mysql(timestamp, unixtime, packettype, subtype, seqnbr, battery, signal, data1, data2, data3, 
	data4, data5, data6, data7, data8, data9, data10, data11, data12, data13):
	"""
	Insert data to MySQL.
	"""

	db = None

	try:

		if data13 == 0:
			data13 = "0000-00-00 00:00:00"

		db = MySQLdb.connect(config.mysql_server, config.mysql_username, config.mysql_password, config.mysql_database)
		cursor = db.cursor()
		sql = """
			INSERT INTO rfxcmd (datetime, unixtime, packettype, subtype, seqnbr, battery, rssi, processed, data1, data2, data3, data4,
				data5, data6, data7, data8, data9, data10, data11, data12, data13)
			VALUES ('%s','%s','%s','%s','%s','%s','%s',0,'%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')
			""" % (timestamp, unixtime, packettype, subtype, int(seqnbr,16), battery, signal, data1, data2, data3, data4, data5, data6, data7, 
				data8, data9, data10, data11, data12, data13)
		
		cursor.execute(sql)
		db.commit()

	except MySQLdb.Error, e:

		logger.error("SqLite error: %d: %s" % (e.args[0], e.args[1]))
		print "MySQL error %d: %s" % (e.args[0], e.args[1])
		sys.exit(1)

	finally:
		if db:
			db.close()

# ----------------------------------------------------------------------------

def insert_sqlite(timestamp, unixtime, packettype, subtype, seqnbr, battery, signal, data1, data2, data3, 
	data4, data5, data6, data7, data8, data9, data10, data11, data12, data13):
	"""
	Insert data to SqLite.
	"""

	cx = None

	try:

		cx = sqlite3.connect(config.sqlite_database)
		cu = cx.cursor()
		sql = """
			INSERT INTO '%s' (datetime, unixtime, packettype, subtype, seqnbr, battery, rssi, processed, data1, data2, data3, data4,
				data5, data6, data7, data8, data9, data10, data11, data12, data13)
			VALUES('%s','%s','%s','%s','%s','%s','%s',0,'%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')
			""" % (config.sqlite_table, timestamp, unixtime, packettype, subtype, int(seqnbr,16), battery, signal, data1, data2, data3, 
				data4, data5, data6, data7, data8, data9, data10, data11, data12, data13)

		cu.executescript(sql)
		cx.commit()
				
	except sqlite3.Error, e:

		if cx:
			cx.rollback()
			
		logger.error("SqLite error: %s" % e.args[0])
		print "SqLite error: %s" % e.args[0]
		sys.exit(1)
			
	finally:
		if cx:
			cx.close()

# ----------------------------------------------------------------------------

def decodePacket(message):
	"""
	Decode incoming RFXtrx message.
	"""
	
	timestamp = time.strftime('%Y-%m-%d %H:%M:%S')
	unixtime_utc = int(time.time())

	decoded = False
	db = ""
	
	# Verify incoming message
	if not test_rfx( ByteToHex(message) ):
		logger.error("The incoming message is invalid (" + ByteToHex(message) + ")")
		if cmdarg.printout_complete == True:
			print "Error: The incoming message is invalid"
			return
			
	packettype = ByteToHex(message[1])

	if len(message) > 2:
		subtype = ByteToHex(message[2])
	
	if len(message) > 3:
		seqnbr = ByteToHex(message[3])

	if len(message) > 4:
		id1 = ByteToHex(message[4])
	
	if len(message) > 5:
		id2 = ByteToHex(message[5])
	
	if cmdarg.printout_complete:
		print "Packettype\t\t= " + rfx.rfx_packettype[packettype]

	# ---------------------------------------
	# 0x0 - Interface Control
	# ---------------------------------------
	if packettype == '00':
		
		decoded = True
	
	# ---------------------------------------
	# 0x01 - Interface Message
	# ---------------------------------------
	if packettype == '01':
		
		decoded = True
		
		if cmdarg.printout_complete:
			
			data = {
			'packetlen' : ByteToHex(message[0]),
			'packettype' : ByteToHex(message[1]),
			'subtype' : ByteToHex(message[2]),
			'seqnbr' : ByteToHex(message[3]),
			'cmnd' : ByteToHex(message[4]),
			'msg1' : ByteToHex(message[5]),
			'msg2' : ByteToHex(message[6]),
			'msg3' : ByteToHex(message[7]),
			'msg4' : ByteToHex(message[8]),
			'msg5' : ByteToHex(message[9]),
			'msg6' : ByteToHex(message[10]),
			'msg7' : ByteToHex(message[11]),
			'msg8' : ByteToHex(message[12]),
			'msg9' : ByteToHex(message[13])
			}

			# Subtype
			if data['subtype'] == '00':
				print "Subtype\t\t\t= Interface response"
			else:
				print "Subtype\t\t\t= Unknown type (" + data['packettype'] + ")"
		
			# Seq
			print "Sequence nbr\t\t= " + data['seqnbr']
		
			# Command
			print "Response on cmnd\t= " + rfx.rfx_cmnd[data['cmnd']]
		
			# MSG 1
			print "Transceiver type\t= " + rfx.rfx_subtype_01_msg1[data['msg1']]
		
			# MSG 2
			print "Firmware version\t= " + str(int(data['msg2'],16))
			
			if testBit(int(data['msg3'],16),7) == 128:
				print "Display undecoded\t= On"
			else:
				print "Display undecoded\t= Off"

			print "Protocols:"
		
			# MSG 3
			if testBit(int(data['msg3'],16),0) == 1:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg3['1']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg3['1']
				
			if testBit(int(data['msg3'],16),1) == 2:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg3['2']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg3['2']
				
			if testBit(int(data['msg3'],16),2) == 4:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg3['4']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg3['4']
				
			if testBit(int(data['msg3'],16),3) == 8:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg3['8']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg3['8']
				
			if testBit(int(data['msg3'],16),4) == 16:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg3['16']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg3['16']
				
			if testBit(int(data['msg3'],16),5) == 32:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg3['32']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg3['32']
				
			if testBit(int(data['msg3'],16),6) == 64:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg3['64']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg3['64']
		
			# MSG 4
			if testBit(int(data['msg4'],16),0) == 1:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg4['1']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg4['1']

			if testBit(int(data['msg4'],16),1) == 2:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg4['2']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg4['2']

			if testBit(int(data['msg4'],16),2) == 4:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg4['4']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg4['4']

			if testBit(int(data['msg4'],16),3) == 8:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg4['8']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg4['8']

			if testBit(int(data['msg4'],16),4) == 16:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg4['16']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg4['16']

			if testBit(int(data['msg4'],16),5) == 32:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg4['32']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg4['32']

			if testBit(int(data['msg4'],16),6) == 64:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg4['64']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg4['64']

			if testBit(int(data['msg4'],16),7) == 128:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg4['128']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg4['128']

			# MSG 5
			if testBit(int(data['msg5'],16),0) == 1:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg5['1']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg5['1']

			if testBit(int(data['msg5'],16),1) == 2:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg5['2']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg5['2']

			if testBit(int(data['msg5'],16),2) == 4:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg5['4']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg5['4']

			if testBit(int(data['msg5'],16),3) == 8:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg5['8']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg5['8']

			if testBit(int(data['msg5'],16),4) == 16:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg5['16']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg5['16']

			if testBit(int(data['msg5'],16),5) == 32:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg5['32']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg5['32']

			if testBit(int(data['msg5'],16),6) == 64:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg5['64']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg5['64']

			if testBit(int(data['msg5'],16),7) == 128:
				print "Enabled\t\t\t" + rfx.rfx_subtype_01_msg5['128']
			else:
				print "Disabled\t\t" + rfx.rfx_subtype_01_msg5['128']
		
	# ---------------------------------------
	# 0x02 - Receiver/Transmitter Message
	# ---------------------------------------
	if packettype == '02':
		
		decoded = True
		
		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_02[subtype]
			print "Seqnbr\t\t\t= " + seqnbr

			if subtype == '01':
				print "Message\t\t\t= " + rfx.rfx_subtype_02_msg1[id1]
		
		# CSV
		if cmdarg.printout_csv == True:
			if subtype == '00':
				sys.stdout.write("%s;%s;%s;%s\n" % (timestamp, packettype, subtype, seqnbr ) )
			else:
				sys.stdout.write("%s;%s;%s;%s;%s\n" % (timestamp, packettype, subtype, seqnbr, id1 ) )
			sys.stdout.flush()
			
		# MYSQL
		if config.mysql_active:
			if subtype == '00':
				insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, 255, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
			else:
				insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, 255, str(id1), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			if subtype == '00':
				insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, 255, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
			else:
				insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, 255, str(id1), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)

	# ---------------------------------------
	# 0x03 - Undecoded Message
	# ---------------------------------------
	if packettype == '03':
		
		decoded = True
		
		indata = ByteToHex(message)

		# remove all spaces
		for x in string.whitespace:
			indata = indata.replace(x,"")

		indata = indata[4:]

		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_03[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Message\t\t\t= " + indata

		# CSV
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s\n" % (timestamp, packettype, subtype, seqnbr, indata ))
			sys.stdout.flush()
			
		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$message$", indata )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, 255, indata, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, 255, indata, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)

	# ---------------------------------------
	# 0x10 Lighting1
	# ---------------------------------------
	if packettype == '10':

		decoded = True
		
		# DATA
		housecode = rfx.rfx_subtype_10_housecode[ByteToHex(message[4])]
		unitcode = int(ByteToHex(message[5]), 16)
		command = rfx.rfx_subtype_10_cmnd[ByteToHex(message[6])]
		signal = decodeSignal(message[7])

		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_10[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Housecode\t\t= " + housecode
			print "Unitcode\t\t= " + str(unitcode)
			print "Command\t\t\t= " + command
			print "Signal level\t\t= " + str(signal)

		# CSV
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, unixtime_utc, packettype, subtype, seqnbr, str(signal), housecode, command, str(unitcode) ))
			sys.stdout.flush()
		
		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$housecode$", str(housecode) )
					action = action.replace("$unitcode$", str(unitcode) )
					action = action.replace("$command$", command )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, housecode, 0, command, unitcode, 0, 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, housecode, 0, command, unitcode, 0, 0, 0, 0, 0, 0, 0, 0, 0)

	# ---------------------------------------
	# 0x11 Lighting2
	# ---------------------------------------
	if packettype == '11':

		decoded = True
		
		# DATA
		sensor_id = ByteToHex(message[4]) + ByteToHex(message[5]) + ByteToHex(message[6]) + ByteToHex(message[7])
		unitcode = int(ByteToHex(message[8]),16)
		command = rfx.rfx_subtype_11_cmnd[ByteToHex(message[9])]
		dimlevel = rfx.rfx_subtype_11_dimlevel[ByteToHex(message[10])]
		signal = decodeSignal(message[11])

		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_11[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Unitcode\t\t= " + str(unitcode)
			print "Command\t\t\t= " + command
			print "Dim level\t\t= " + dimlevel + "%"
			print "Signal level\t\t= " + str(signal)
		
		# CSV
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, unixtime_utc, packettype, subtype, seqnbr, str(signal), sensor_id, command, str(unitcode), dimlevel ))
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$unitcode$", str(unitcode) )
					action = action.replace("$command$", command )
					action = action.replace("$dimlevel$", dimlevel )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, sensor_id, 0, command, unitcode, int(dimlevel), 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, sensor_id, 0, command, unitcode, int(dimlevel), 0, 0, 0, 0, 0, 0, 0, 0)

	# ---------------------------------------
	# 0x12 Lighting3
	# ---------------------------------------
	if packettype == '12':

		decoded = True
		
		# DATA
		system = ByteToHex(message[4])

		if testBit(int(ByteToHex(message[5]),16),0) == 1:
			channel = 1
		elif testBit(int(ByteToHex(message[5]),16),1) == 2:
			channel = 2
		elif testBit(int(ByteToHex(message[5]),16),2) == 4:
			channel = 3
		elif testBit(int(ByteToHex(message[5]),16),3) == 8:
			channel = 4
		elif testBit(int(ByteToHex(message[5]),16),4) == 16:
			channel = 5
		elif testBit(int(ByteToHex(message[5]),16),5) == 32:
			channel = 6
		elif testBit(int(ByteToHex(message[5]),16),6) == 64:
			channel = 7
		elif testBit(int(ByteToHex(message[5]),16),7) == 128:
			channel = 8
		elif testBit(int(ByteToHex(message[6]),16),0) == 1:
			channel = 9
		elif testBit(int(ByteToHex(message[6]),16),1) == 2:
			channel = 10
		else:
			channel = 255

		command = rfx.rfx_subtype_12_cmnd[ByteToHex(message[7])]
		battery = decodeBattery(message[8])
		signal = decodeSignal(message[8])

		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_12[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "System\t\t\t= " + system
			print "Channel\t\t\t= " + str(channel)
			print "Command\t\t\t= " + command
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)

		# CSV 
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;\n" %(timestamp, packettype, subtype, seqnbr, str(battery), str(signal), str(system), command, str(channel) ))
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$system$", str(system) )
					action = action.replace("$channel$", str(channel) )
					action = action.replace("$command$", command )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, str(system), 0, command, str(channel), 0, 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, str(system), 0, command, str(channel), 0, 0, 0, 0, 0, 0, 0, 0, 0)

	# ---------------------------------------
	# 0x13 Lighting4
	# ---------------------------------------
	if packettype == '13':

		decoded = True

		# DATA
		code = ByteToHex(message[4]) + ByteToHex(message[5]) + ByteToHex(message[6])
		code1 = dec2bin(int(ByteToHex(message[4]),16))
		code2 = dec2bin(int(ByteToHex(message[5]),16))
		code3 = dec2bin(int(ByteToHex(message[6]),16))
		code_bin = code1 + " " + code2 + " " + code3
		pulse = ((int(ByteToHex(message[7]),16) * 256) + int(ByteToHex(message[8]),16))
		signal = decodeSignal(message[9])		
		
		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_13[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Code\t\t\t= " + code
			print "S1-S24\t\t\t= "  + code_bin
			print "Pulse\t\t\t= " + str(pulse) + " usec"
			print "Signal level\t\t= " + str(signal)

		# CSV
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, packettype, subtype, seqnbr, code, code_bin, str(pulse), str(signal) ))
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$code$", code_bin )
					action = action.replace("$pulse$", str(pulse) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

	# ---------------------------------------
	# 0x14 Lighting5
	# ---------------------------------------
	if packettype == '14':

		decoded = True
		
		# DATA
		sensor_id = id1 + id2 + ByteToHex(message[6])
		unitcode = int(ByteToHex(message[7]),16)
		
		if subtype == '00':
			command = rfx.rfx_subtype_14_cmnd0[ByteToHex(message[8])]
		elif subtype == '01':
			command = rfx.rfx_subtype_14_cmnd1[ByteToHex(message[8])]
		elif subtype == '02':
			command = rfx.rfx_subtype_14_cmnd2[ByteToHex(message[8])]
		else:
			command = "Unknown"
		
		if subtype == "00":
			level = ByteToHex(message[9])
		else:
			level = 0
		
		signal = decodeSignal(message[10])
		
		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_14[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Unitcode\t\t= " + str(unitcode)
			print "Command\t\t\t= " + command
			
			if subtype == '00':
				print "Level\t\t\t= " + level
			
			print "Signal level\t\t= " + str(signal)
	
		# CSV
		if cmdarg.printout_csv:
			if subtype == '00':
				sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, packettype, subtype, seqnbr, sensor_id, str(unitcode), command, level, str(signal) ))
			else:
				sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, packettype, subtype, seqnbr, sensor_id, str(unitcode), command, str(signal) ))
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$unitcode$", str(unitcode) )
					action = action.replace("$command$", command )
					if subtype == '00':			
						action = action.replace("$level$", level )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 0, signal, sensor_id, 0, command, str(unitcode), level, 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 0, signal, sensor_id, 0, command, str(unitcode), level, 0, 0, 0, 0, 0, 0, 0, 0)

	# ---------------------------------------
	# 0x15 Lighting6
	# Credit: Dimitri Clatot
	# ---------------------------------------
	if packettype == '15':

		decoded = True

		# DATA
		sensor_id = id1 + id2
		groupcode = rfx.rfx_subtype_15_groupcode[ByteToHex(message[6])]
		unitcode = int(ByteToHex(message[7]),16)
		command = rfx.rfx_subtype_15_cmnd[ByteToHex(message[8])]
		command_seqnbr = ByteToHex(message[9])
		signal = decodeSignal(message[11])

		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_15[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "ID\t\t\t= "  + sensor_id
			print "Groupcode\t\t= " + groupcode
			print "Unitcode\t\t= " + str(unitcode)
			print "Command\t\t\t= " + command
			print "Command seqnbr\t\t= " + command_seqnbr
			print "Signal level\t\t= " + str(signal)

		# CSV
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, packettype, subtype, seqnbr, sensor_id, str(signal), groupcode, command, str(unitcode), str(command_seqnbr) ))
			sys.stdout.flush()
			
		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$groupcode$", groupcode )
					action = action.replace("$unitcode$", str(unitcode) )
					action = action.replace("$command$", command )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, sensor_id, groupcode, command, unitcode, command_seqnbr, 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, sensor_id, groupcode, command, unitcode, command_seqnbr, 0, 0, 0, 0, 0, 0, 0, 0)

	# ---------------------------------------
	# 0x18 Curtain1 (Transmitter only)
	# ---------------------------------------
	if packettype == '18':

		decoded = True

		# PRINTOUT		
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_18[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "This sensor is not completed, please send printout to sebastian.sjoholm@gmail.com"

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

	# ---------------------------------------
	# 0x19 Blinds1
	# ---------------------------------------
	if packettype == '19':

		decoded = True
		
		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_19[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "This sensor is not completed, please send printout to sebastian.sjoholm@gmail.com"

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

	# ---------------------------------------
	# 0x20 Security1
	# Credit: Dimitri Clatot
	# ---------------------------------------
	if packettype == '20':

		decoded = True
		
		# DATA
		sensor_id = id1 + id2 + ByteToHex(message[6])
		status = rfx.rfx_subtype_20_status[ByteToHex(message[7])]
		signal = decodeSignal(message[8])
		battery = decodeBattery(message[8])

		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_20[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= "  + sensor_id
			print "Status\t\t\t= " + status
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)

		# CSV
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, unixtime_utc, packettype, subtype, seqnbr, str(battery), str(signal), sensor_id, status ) )
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$status$", status )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
			
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, status, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, status, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)

	# ---------------------------------------
	# 0x28 Camera1
	# ---------------------------------------
	if packettype == '28':

		decoded = True
		
		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_28[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "This sensor is not completed, please send printout to sebastian.sjoholm@gmail.com"

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

	# ---------------------------------------
	# 0x30 Remote control and IR
	# ---------------------------------------
	if packettype == '30':

		decoded = True

		# Command type
		if subtype == '04':
			if ByteToHex(message[7]) == '00':
				cmndtype = "PC"
			elif ByteToHex(message[7]) == '01':
				cmndtype = "AUX1"
			elif ByteToHex(message[7]) == '02':
				cmndtype = "AUX2"
			elif ByteToHex(message[7]) == '03':
				cmndtype = "AUX3"
			elif ByteToHex(message[7]) == '04':
				cmndtype = "AUX4"
			else:
				cmndtype = "Unknown"

		# Command
		if subtype == '00':
			command = rfx.rfx_subtype_30_atiremotewonder[ByteToHex(message[5])]
		elif subtype == '01':
			command = "Not implemented in RFXCMD"
		elif subtype == '02':
			command = rfx.rfx_subtype_30_medion[ByteToHex(message[5])]
		elif subtype == '03':
			command = "Not implemented in RFXCMD"
		elif subtype == '04':
			command = "Not implemented in RFXCMD"

		toggle = ByteToHex(message[6])
		
		if subtype == '00' or subtype == '02' or subtype == '03':
			signal = decodeSignal(message[6])

		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_30[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + id1
			print "Command\t\t\t= " + command

			if subtype == '04':
				print "Toggle\t\t\t= " + toggle

			if subtype == '04':
				print "CommandType\t= " + cmndtype

			print "Signal level\t\t= " + str(signal)

		# CSV 
		if cmdarg.printout_csv:
			if subtype == '00' or subtype == '02':
				sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, unixtime_utc, packettype, subtype, seqnbr, str(signal), id1, command))
			elif subtype == '04' or subtype == '01' or subtype == '03':
				command = "Not implemented in RFXCMD"
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", id1 )
					action = action.replace("$command$", command )
					if subtype == '04':
						action = action.replace("$toggle$", toggle )
						action = action.replace("$command$", cmndtype )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			if subtype == '00' or subtype == '02':
				insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 0, signal, id1, 0, command, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
			elif subtype == '04' or subtype == '01' or subtype == '03':
				command = "Not implemented in RFXCMD"

		# SQLITE
		if config.sqlite_active:
			if subtype == '00' or subtype == '02':
				insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 0, signal, id1, 0, command, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
			elif subtype == '04' or subtype == '01' or subtype == '03':
				command = "Not implemented in RFXCMD"

	# ---------------------------------------
	# 0x40 - Thermostat1
	# Credit: Jean-François Pucheu
	# ---------------------------------------
	if packettype == '40':

		decoded = True

		# DATA
		sensor_id = id1 + id2
		temperature = int(ByteToHex(message[6]), 16)
		temperature_set = int(ByteToHex(message[7]), 16)
		status_temp = str(testBit(int(ByteToHex(message[8]),16),0) + testBit(int(ByteToHex(message[8]),16),1))
		status = rfx.rfx_subtype_40_status[status_temp]
		if testBit(int(ByteToHex(message[8]),16),7) == 128:
			mode = rfx.rfx_subtype_40_mode['1']
		else:
			mode = rfx.rfx_subtype_40_mode['0']
		signal = decodeSignal(message[9])

		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_40[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Temperature\t\t= " + str(temperature) + " C"
			print "Temperature set\t\t= " + str(temperature_set) + " C"
			print "Mode\t\t\t= " + mode
			print "Status\t\t\t= " + status
			print "Signal level\t\t= " + str(signal)

		# CSV 
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, unixtime_utc, packettype, subtype, seqnbr, str(signal), mode, status, str(temperature_set), str(temperature) ))
			sys.stdout.flush()
	
		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$temperature$", str(temperature) )
					action = action.replace("$temperatureset$", str(temperature_set) )
					action = action.replace("$mode$", mode )
					action = action.replace("$status$", status )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, sensor_id, mode, status, 0, 0, 0, temperature_set, temperature, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, sensor_id, mode, status, 0, 0, 0, temperature_set, temperature, 0, 0, 0, 0, 0)

		# XPL
		if config.xpl_active:
			xpl.send(config.xpl_host, 'device=Thermostat.'+sensor_id+'\ntype=temperature\ncurrent='+temperature+'\nunits=C')
			xpl.send(config.xpl_host, 'device=Thermostat.'+sensor_id+'\ntype=temperature_set\ncurrent='+temperature_set+'\nunits=C')
			xpl.send(config.xpl_host, 'device=Thermostat.'+sensor_id+'\ntype=mode\ncurrent='+mode+'\n')
			xpl.send(config.xpl_host, 'device=Thermostat.'+sensor_id+'\ntype=status\ncurrent='+mode+'\n')
			xpl.send(config.xpl_host, 'device=Thermostat.'+sensor_id+'\ntype=battery\ncurrent='+str(battery*10)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=Thermostat.'+sensor_id+'\ntype=signal\ncurrent='+str(signal*10)+'\nunits=%')

	# ---------------------------------------
	# 0x41 Thermostat2
	# ---------------------------------------
	if packettype == '41':

		decoded = True
		
		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_41[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			# TODO

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

	# ---------------------------------------
	# 0x42 Thermostat3
	# ---------------------------------------
	if packettype == '42':

		logger.debug("PacketType 0x42")
		
		decoded = True

		# DATA
		if subtype == '00':
			unitcode = ByteToHex(message[4])
		elif subtype == '01':
			unitcode = ByteToHex(message[4]) + ByteToHex(message[5]) + ByteToHex(message[6])
		else:
			unitcode = "00"
		
		logger.debug("Unitcode: " + unitcode)
		
		if subtype == '00':
			command = rfx.rfx_subtype_42_cmd00[ByteToHex(message[7])]
		elif subtype == '01':
			command = rfx.rfx_subtype_42_cmd01[ByteToHex(message[7])]
		else:
			command = '0'

		logger.debug("Command: " + command)

		signal = decodeSignal(message[8])

		# PRINTOUT
		if cmdarg.printout_complete:
			logger.debug("Printout data")
			print "Subtype\t\t\t= " + rfx.rfx_subtype_42[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Unitcode\t\t= " + unitcode
			print "Command\t\t\t= " + command
			print "Signal level\t\t= " + str(signal)

		# CSV 
		if cmdarg.printout_csv:
			logger.debug("Output in CSV")
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s\n" %(timestamp, packettype, subtype, seqnbr, str(signal), unitcode, command))
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			logger.debug("Check trigger")
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$unitcode$", unitcode )
					action = action.replace("$command$", command )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, unitcode, 0, command, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, unitcode, 0, command, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)

		# XPL
		if config.xpl_active:
			xpl.send(config.xpl_host, 'device=Thermostat.'+unitcode+'\ntype=command\ncurrent='+command+'\nunits=C')
			xpl.send(config.xpl_host, 'device=Thermostat.'+unitcode+'\ntype=signal\ncurrent='+str(signal*10)+'\nunits=%')

		logger.debug("PacketType 0x42, done.")

	# ---------------------------------------
	# 0x50 - Temperature sensors
	# ---------------------------------------
	if packettype == '50':
	
		decoded = True

		# DATA
		sensor_id = id1 + id2
		temperature = decodeTemperature(message[6], message[7])
		signal = decodeSignal(message[8])
		battery = decodeBattery(message[8])

		# PRINTOUT
		if cmdarg.printout_complete:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_50[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Temperature\t\t= " + temperature + " C"
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)

		# CSV
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, unixtime_utc, packettype, subtype, seqnbr, sensor_id, str(battery), str(signal), temperature ))
			sys.stdout.flush()
			
		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$temperature$", str(temperature) )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, 0, 0, 0, 0, 0, float(temperature), 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, 0, 0, 0, 0, 0, float(temperature), 0, 0, 0, 0, 0)

		# XPL
		if config.xpl_active:
			xpl.send(config.xpl_host, 'device=Temp.'+sensor_id+'\ntype=temp\ncurrent='+temperature+'\nunits=C')
			xpl.send(config.xpl_host, 'device=Temp.'+sensor_id+'\ntype=battery\ncurrent='+str(battery*10)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=Temp.'+sensor_id+'\ntype=signal\ncurrent='+str(signal*10)+'\nunits=%')

	# ---------------------------------------
	# 0x51 - Humidity sensors
	# ---------------------------------------

	if packettype == '51':
		
		decoded = True

		# DATA
		sensor_id = id1 + id2
		humidity = int(ByteToHex(message[6]),16)
		humidity_status = rfx.rfx_subtype_51_humstatus[ByteToHex(message[7])]
		signal = decodeSignal(message[8])
		battery = decodeBattery(message[8])
		
		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_51[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Humidity\t\t= " + str(humidity)
			print "Humidity Status\t\t= " + humidity_status
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)
		
		# CSV
		if cmdarg.printout_csv:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n" %
							(timestamp, unixtime_utc, packettype, subtype, seqnbr, sensor_id, humidity_status, str(humidity), str(battery), str(signal)) )
			sys.stdout.flush()
		
		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$humidity$", str(humidity) )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, humidity_status, humidity, 0, 0, 0, 0, 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, humidity_status, humidity, 0, 0, 0, 0, 0, 0, 0, 0, 0)

		# XPL
		if config.xpl_active:
			xpl.send(config.xpl_host, 'device=Hum.'+sensor_id+'\ntype=humidity\ncurrent='+str(humidity)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=Hum.'+sensor_id+'\ntype=battery\ncurrent='+str(battery*10)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=Hum.'+sensor_id+'\ntype=signal\ncurrent='+str(signal*10)+'\nunits=%')

	# ---------------------------------------
	# 0x52 - Temperature and humidity sensors
	# ---------------------------------------
	if packettype == '52':
		
		decoded = True
		logger.debug("PacketType 0x52")

		# DATA
		sensor_id = id1 + id2
		temperature = decodeTemperature(message[6], message[7])
		humidity = int(ByteToHex(message[8]),16)
		humidity_status = rfx.rfx_subtype_52_humstatus[ByteToHex(message[9])]
		signal = decodeSignal(message[10])
		battery = decodeBattery(message[10])
		
		# PRINTOUT
		if cmdarg.printout_complete == True:
			logger.debug("Print data stdout")
			print "Subtype\t\t\t= " + rfx.rfx_subtype_52[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Temperature\t\t= " + temperature + " C"
			print "Humidity\t\t= " + str(humidity) + "%"
			print "Humidity Status\t\t= " + humidity_status
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)
		
		# CSV
		if cmdarg.printout_csv == True:
			logger.debug("CSV Output")
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n" %
							(timestamp, unixtime_utc, packettype, subtype, seqnbr, sensor_id, humidity_status,
							temperature, str(humidity), str(battery), str(signal)) )
			sys.stdout.flush()
		
		# TRIGGER
		if config.trigger_active:
			logger.debug("Check trigger")			
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$temperature$", str(temperature) )
					action = action.replace("$humidity$", str(humidity) )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# GRAPHITE
		if config.graphite_active == True:
			logger.debug("Send to Graphite")
			now = int( time.time() )
			linesg=[]
			linesg.append("%s.%s.temperature %s %d" % ( 'rfxcmd', sensor_id, temperature,now))
			linesg.append("%s.%s.humidity %s %d" % ( 'rfxcmd', sensor_id, humidity,now))
			linesg.append("%s.%s.battery %s %d" % ( 'rfxcmd', sensor_id, battery,now))
			linesg.append("%s.%s.signal %s %d"% ( 'rfxcmd', sensor_id, signal,now))
			send_graphite(config.graphite_server, config.graphite_port, linesg)

		# MYSQL
		if config.mysql_active:
			logger.debug("Send to MySQL")
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, humidity_status, humidity, 0, 0, 0, float(temperature), 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			logger.debug("Send to Sqlite")
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, humidity_status, humidity, 0, 0, 0, float(temperature), 0, 0, 0, 0, 0)

		# XPL
		if config.xpl_active:
			logger.debug("Send to xPL")
			xpl.send(config.xpl_host, 'device=HumTemp.'+sensor_id+'\ntype=temp\ncurrent='+temperature+'\nunits=C')
			xpl.send(config.xpl_host, 'device=HumTemp.'+sensor_id+'\ntype=humidity\ncurrent='+str(humidity)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=HumTemp.'+sensor_id+'\ntype=battery\ncurrent='+str(battery*10)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=HumTemp.'+sensor_id+'\ntype=signal\ncurrent='+str(signal*10)+'\nunits=%')

	# ---------------------------------------
	# 0x53 - Barometric
	# RESERVED FOR FUTURE
	# ---------------------------------------

	# ---------------------------------------
	# 0x54 - Temperature, humidity and barometric sensors
	# Credit: Jean-Baptiste Bodart
	# ---------------------------------------
	if packettype == '54':
		
		logger.debug("PacketType 0x54")
		
		decoded = True

		# Sensor id
		sensor_id = id1 + id2
		logger.debug("sensor_id: " + sensor_id)

		# Temperature
		temperature = decodeTemperature(message[6], message[7])
		logger.debug("temperature: " + temperature)
		
		# Humidity
		humidity = int(ByteToHex(message[8]),16)
		logger.debug("humidity: " + str(humidity))
		
		try:
			humidity_status = rfx.rfx_subtype_54_humstatus[ByteToHex(message[9])]
			logger.debug("humidity_status: " + humidity_status)
		except:
			logger.warning("Humidity status is unknown (" + ByteToHex(message) + ")")
			humidity_status = "Unknown"

		# Barometric pressure
		barometric_high = ByteToHex(message[10])
		barometric_low = ByteToHex(message[11])
		barometric_high = clearBit(int(barometric_high,16),7)
		barometric_high = barometric_high << 8
		barometric = ( barometric_high + int(barometric_low,16) )
		
		# Forecast
		forecast = rfx.rfx_subtype_54_forecast[ByteToHex(message[12])]
		
		# Battery & Signal
		signal = decodeSignal(message[13])
		battery = decodeBattery(message[13])
		
		# PRINTOUT
		if cmdarg.printout_complete == True:
			logger.debug("Printout")
			print "Subtype\t\t\t= " + rfx.rfx_subtype_54[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Temperature\t\t= " + temperature + " C"
			print "Humidity\t\t= " + str(humidity)
			print "Humidity Status\t\t= " + humidity_status			
			print "Barometric pressure\t= " + str(barometric)
			print "Forecast Status\t\t= " + forecast
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)
		
		# CSV
		if cmdarg.printout_csv == True:
			logger.debug("CSV")
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n" %
							(timestamp, unixtime_utc, packettype, subtype, seqnbr, str(battery), str(signal), sensor_id,
							forecast, humidity_status, str(humidity), str(barometric), str(temperature)))
			sys.stdout.flush()
		
		# TRIGGER
		if config.trigger_active:	
			logger.debug("Trigger")
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$temperature$", str(temperature) )
					action = action.replace("$humidity$", str(humidity) )
					action = action.replace("$baromatric$", str(baromatric) )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, forecast, humidity_status, humidity, barometric, 0, 0, float(temperature), 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, forecast, humidity_status, humidity, barometric, 0, 0, float(temperature), 0, 0, 0, 0, 0)

		# XPL
		if config.xpl_active:
			xpl.send(config.xpl_host, 'device=HumTempBaro.'+sensor_id+'\ntype=temp\ncurrent='+temperature+'\nunits=C')
			xpl.send(config.xpl_host, 'device=HumTempBaro.'+sensor_id+'\ntype=humidity\ncurrent='+str(humidity)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=HumTempBaro.'+sensor_id+'\ntype=humidity\ncurrent='+str(barometric)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=HumTempBaro.'+sensor_id+'\ntype=battery\ncurrent='+str(battery*10)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=HumTempBaro.'+sensor_id+'\ntype=signal\ncurrent='+str(signal*10)+'\nunits=%')

	# ---------------------------------------
	# 0x55 - Rain sensors
	# ---------------------------------------
	
	if packettype == '55':
		
		logger.debug("Decode packetType 0x" + str(packettype) + " - Start")
		
		decoded = True

		# Sensor id
		sensor_id = id1 + id2

		# Rain rate
		rainrate_high = ByteToHex(message[6])
		rainrate_low = ByteToHex(message[7])

		# Rain total
		raintotal1 = ByteToHex(message[8])
		raintotal2 = ByteToHex(message[9])
		raintotal3 = ByteToHex(message[10])
		
		# Battery & Signal	
		signal = decodeSignal(message[11])
		battery = decodeBattery(message[11])
		
		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_55[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			
			if subtype == '1':
				print "Rain rate\t\t= Not implemented in rfxcmd, need example"
			elif subtype == '2':
				print "Rain rate\t\t= Not implemented in rfxcmd, need example"
			else:
				print "Rain rate\t\t= Not supported"

			print "Raintotal:\t\t= " + str(int(raintotal1,16))
			print "Raintotal:\t\t= " + str(int(raintotal2,16))
			print "Raintotal:\t\t= " + str(int(raintotal3,16))
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)
		
		# CSV		
		if cmdarg.printout_csv == True:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n" %
							( timestamp, packettype, subtype, seqnbr, id1, id2,
							str(int(rainrate_high,16)), str(int(raintotal1,16)), 
							str(battery), str(signal) ) )
			sys.stdout.flush()
		
		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		"""			
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, 0, 0, 0, 0, 0, float(temperature), av_speed, gust, direction, float(windchill), 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, 0, 0, 0, 0, 0, float(temperature), av_speed, gust, direction, float(windchill), 0)
		"""

		logger.debug("Decode packetType 0x" + str(packettype) + " - Done")


	# ---------------------------------------
	# 0x56 - Wind sensors
	# ---------------------------------------
	if packettype == '56':
		
		decoded = True

		# DATA
		sensor_id = id1 + id2
		direction = ( ( int(ByteToHex(message[6]),16) * 256 ) + int(ByteToHex(message[7]),16) )
		if subtype <> "05":
			av_speed = ( ( int(ByteToHex(message[8]),16) * 256 ) + int(ByteToHex(message[9]),16) ) * 0.1
		else:
			av_speed = 0;
		gust = ( ( int(ByteToHex(message[10]),16) * 256 ) + int(ByteToHex(message[11]),16) ) * 0.1
		if subtype == "04":
			temperature = decodeTemperature(message[12], message[13])
		else:
			temperature = 0
		if subtype == "04":
			windchill = decodeTemperature(message[14], message[15])
		else:
			windchill = 0
		signal = decodeSignal(message[16])
		battery = decodeBattery(message[16])

		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_56[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Wind direction\t\t= " + str(direction) + " degrees"
			
			if subtype <> "05":
				print "Average wind\t\t= " + str(av_speed) + " mtr/sec"
			
			if subtype == "04":
				print "Temperature\t\t= " + str(temperature) + " C"
				print "Wind chill\t\t= " + str(windchill) + " C" 
			
			print "Windgust\t\t= " + str(gust) + " mtr/sec"
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)

		# CSV
		if cmdarg.printout_csv == True:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n" %
							(timestamp, unixtime_utc, packettype, subtype, seqnbr, str(battery), str(signal), sensor_id, str(temperature), str(av_speed), str(gust), str(direction), str(windchill) ) )
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$direction$", str(direction) )
					if subtype <> "05":
						action = action.replace("$average$", str(av_speed) )
					if subtype == "04":
						action = action.replace("$temperature$", str(temperature) )
						action = action.replace("$windchill$", str(windchill) )
					action = action.replace("$windgust$", str(windgust) )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, 0, 0, 0, 0, 0, float(temperature), av_speed, gust, direction, float(windchill), 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, 0, 0, 0, 0, 0, float(temperature), av_speed, gust, direction, float(windchill), 0)

		# xPL
		if config.xpl_active:
			xpl.send(config.xpl_host, 'device=Wind.'+sensor_id+'\ntype=direction\ncurrent='+str(direction)+'\nunits=Degrees')
			
			if subtype <> "05":
				xpl.send(config.xpl_host, 'device=Wind.'+sensor_id+'\ntype=Averagewind\ncurrent='+str(av_speed)+'\nunits=mtr/sec')
			
			if subtype == "04":
				xpl.send(config.xpl_host, 'device=Wind.'+sensor_id+'\ntype=temperature\ncurrent='+str(temperature)+'\nunits=C')
				xpl.send(config.xpl_host, 'device=Wind.'+sensor_id+'\ntype=windchill\ncurrent='+str(windchill)+'\nunits=C')
			
			xpl.send(config.xpl_host, 'device=Wind.'+sensor_id+'\ntype=windgust\ncurrent='+str(gust)+'\nunits=mtr/sec')
			xpl.send(config.xpl_host, 'device=Wind.'+sensor_id+'\ntype=battery\ncurrent='+str(battery*10)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=Wind.'+sensor_id+'\ntype=signal\ncurrent='+str(signal*10)+'\nunits=%')

	# ---------------------------------------
	# 0x57 UV Sensor
	# ---------------------------------------

	if packettype == '57':

		decoded = True

		# DATA
		sensor_id = id1 + id2
		uv = int(ByteToHex(message[6]), 16) * 10
		temperature = decodeTemperature(message[6], message[8])
		signal = decodeSignal(message[9])
		battery = decodeBattery(message[9])

		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_57[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "UV\t\t\t= " + str(uv)
			if subtype == '03':
				print "Temperature\t\t= " + temperature + " C"
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)

		# CSV
		if cmdarg.printout_csv:
			if subtype == '03':
				sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, packettype, subtype, seqnbr, sensor_id, str(uv), temperature, str(battery), str(signal) ) )
			else:
				sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, packettype, subtype, seqnbr, sensor_id, str(uv), str(battery), str(signal) ) )
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$uv$", str(temperature) )
					if subtype == '03':
						action = action.replace("$temperature$", str(humidity) )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, 0, str(uv), 0, 0, 0, float(temperature), 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, battery, signal, sensor_id, 0, 0, str(uv), 0, 0, 0, float(temperature), 0, 0, 0, 0, 0)

		# xPL
		if config.xpl_active:
			xpl.send(config.xpl_host, 'device=UV.'+sensor_id+'\ntype=uv\ncurrent='+str(uv)+'\nunits=Index')
			if subtype == "03":
				xpl.send(config.xpl_host, 'device=UV.'+sensor_id+'\ntype=Temperature\ncurrent='+str(temperature)+'\nunits=Celsius')

	# ---------------------------------------
	# 0x59 Current Sensor
	# ---------------------------------------

	if packettype == '59':

		decoded = True

		# DATA
		sensor_id = id1 + id2
		count = int(ByteToHex(message[6]),16)
		channel1 = (int(ByteToHex(message[7]),16) * 0x100 + int(ByteToHex(message[8]),16)) * 0.1
		channel2 = int(ByteToHex(message[9]),16) * 0x100 + int(ByteToHex(message[10]),16)
		channel3 = int(ByteToHex(message[11]),16) * 0x100 + int(ByteToHex(message[12]),16)
		signal = decodeSignal(message[13])
		battery = decodeBattery(message[13])

		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_5A[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Counter\t\t\t= " + str(count)
			print "Channel 1\t\t= " + str(channel1) + "A"
			print "Channel 2\t\t= " + str(channel2) + "A"
			print "Channel 3\t\t= " + str(channel3) + "A"
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)
	
		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$counter$", str(count) )
					action = action.replace("$channel1$", str(channel1) )
					action = action.replace("$channel2$", str(channel2) )
					action = action.replace("$channel3$", str(channel3) )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# XPL
		if config.xpl_active:
			xpl.send(config.xpl_host, 'device=Current.'+sensor_id+'\ntype=channel1\ncurrent='+str(channel1)+'\nunits=A')
			xpl.send(config.xpl_host, 'device=Current.'+sensor_id+'\ntype=channel2\ncurrent='+str(channel2)+'\nunits=A')
			xpl.send(config.xpl_host, 'device=Current.'+sensor_id+'\ntype=channel3\ncurrent='+str(channel3)+'\nunits=A')
			xpl.send(config.xpl_host, 'device=Current.'+sensor_id+'\ntype=battery\ncurrent='+str(battery*10)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=Current.'+sensor_id+'\ntype=signal\ncurrent='+str(signal*10)+'\nunits=%')

	# ---------------------------------------
	# 0x5A Energy sensor
	# Credit: Jean-Michel ROY
	# ---------------------------------------
	if packettype == '5A':

		decoded = True

		# DATA
		sensor_id = id1 + id2
		signal = decodeSignal(message[17])
		battery = decodeBattery(message[17])
		instant = int(ByteToHex(message[7]), 16) * 0x1000000 + int(ByteToHex(message[8]), 16) * 0x10000 + int(ByteToHex(message[9]), 16) * 0x100  + int(ByteToHex(message[10]), 16)
		usage = int ((int(ByteToHex(message[11]), 16) * 0x10000000000 + int(ByteToHex(message[12]), 16) * 0x100000000 +int(ByteToHex(message[13]), 16) * 0x1000000 + int(ByteToHex(message[14]), 16) * 0x10000 + int(ByteToHex(message[15]), 16) * 0x100 + int(ByteToHex(message[16]), 16) ) / 223.666)

		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_5A[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + sensor_id
			print "Instant usage\t\t= " + str(instant) + " Watt"
			print "Total usage\t\t= " + str(usage) + " Wh"
			print "Battery\t\t\t= " + str(battery)
			print "Signal level\t\t= " + str(signal)

		# CSV
		if cmdarg.printout_csv == True:
			sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s\n" %
							(timestamp, unixtime_utc, packettype, subtype, seqnbr, sensor_id,
							str(instant), str(usage), str(battery), str(signal)) )
			sys.stdout.flush()
		
		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", str(sensor_id) )
					action = action.replace("$instant$", str(instant) )
					action = action.replace("$total$", str(usage) )
					action = action.replace("$battery$", str(battery) )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
		
		# XPL
		if config.xpl_active:
			xpl.send(config.xpl_host, 'device=Energy.'+sensor_id+'\ntype=instant_usage\ncurrent='+str(instant)+'\nunits=W')
			xpl.send(config.xpl_host, 'device=Energy.'+sensor_id+'\ntype=total_usage\ncurrent='+str(usage)+'\nunits=Wh')
			xpl.send(config.xpl_host, 'device=Energy.'+sensor_id+'\ntype=battery\ncurrent='+str(battery*10)+'\nunits=%')
			xpl.send(config.xpl_host, 'device=Energy.'+sensor_id+'\ntype=signal\ncurrent='+str(signal*10)+'\nunits=%')

	# ---------------------------------------
	# 0x5B Current + Energy sensor
	# ---------------------------------------
	
	if packettype == '58':

		decoded = True

		# DATA
		sensor_id = id1 + id2
		date_year = ByteToHex(message[6]);
		date_month = ByteToHex(message[7]);
		date_day = ByteToHex(message[8]);
		date_dow = ByteToHex(message[9]);
		time_hour = ByteToHex(message[10]);
		time_min = ByteToHex(message[11]);
		time_sec = ByteToHex(message[12]);
		signal = decodeSignal(message[13])
		battery = decodeBattery(message[13])

		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_58[subtype]
			print "Not implemented in RFXCMD, please send sensor data to sebastian.sjoholm@gmail.com"

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

	# ---------------------------------------
	# 0x70 RFXsensor
	# ---------------------------------------
	if packettype == '70':

		decoded = True

		# DATA
		if subtype == '00':
			temperature = float(decodeTemperature(message[5], message[6]))
			temperature = temperature * 0.1
		else:
			temperature = 0
		if subtype == '01' or subtype == '02':
			voltage_hi = int(ByteToHex(message[5]), 16) * 256
			voltage_lo = int(ByteToHex(message[6]), 16)
			voltage = voltage_hi + voltage_lo
		else:
			voltage = 0
		signal = decodeSignal(message[7])

		if subtype == '03':
			sensor_message = rfx.rfx_subtype_70_msg03[message[6]]

		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_70[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + id1

			if subtype == '00':
				print "Temperature\t\t= " + str(temperature) + " C"

			if subtype == '01' or subtype == '02':
				print "Voltage\t\t\t= " + str(voltage) + " mV"

			if subtype == '03':
				print "Message\t\t\t= " + sensor_message

			print "Signal level\t\t= " + str(signal)

		# CSV
		if cmdarg.printout_csv == True:
			if subtype == '00':
				sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, unixtime_utc, packettype, subtype, seqnbr, str(signal), id1, str(temperature)))
			if subtype == '01' or subtype == '02':
				sys.stdout.write("%s;%s;%s;%s;%s;%s;%s;%s\n" % (timestamp, unixtime_utc, packettype, subtype, seqnbr, str(signal), id1, str(voltage)))
			sys.stdout.flush()

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", id1 )
					if subtype == '00':
						action = action.replace("$temperature$", str(temperature) )
					if subtype == '01' or subtype == '02':
						action = action.replace("$voltage$", str(voltage) )
					if subtype == '03':
						action = action.replace("$message$", sensor_message )
					action = action.replace("$signal$", str(signal) )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return
					
		# MYSQL
		if config.mysql_active:
			insert_mysql(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, id1, ByteToHex(message[5]), ByteToHex(message[6]), 0, 0, 0, voltage, float(temperature), 0, 0, 0, 0, 0)

		# SQLITE
		if config.sqlite_active:
			insert_sqlite(timestamp, unixtime_utc, packettype, subtype, seqnbr, 255, signal, id1, ByteToHex(message[5]), ByteToHex(message[6]), 0, 0, 0, voltage, float(temperature), 0, 0, 0, 0, 0)

	# ---------------------------------------
	# 0x71 RFXmeter
	# ---------------------------------------
	if packettype == '71':

		decoded = True
		
		# DATA
		sensor_id = id1 + id2
		
		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_71[subtype]
			print "Seqnbr\t\t\t= " + seqnbr
			print "Id\t\t\t= " + id1

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					action = action.replace("$id$", id1 )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

	# ---------------------------------------
	# 0x72 FS20
	# ---------------------------------------
	if packettype == '72':
	
		logger.debug("PacketType 0x72")

		decoded = True
		
		# PRINTOUT
		if cmdarg.printout_complete == True:
			print "Subtype\t\t\t= " + rfx.rfx_subtype_72[subtype]
			print "Not implemented in RFXCMD, please send sensor data to sebastian.sjoholm@gmail.com"

		# TRIGGER
		if config.trigger_active:
			for trigger in triggerlist.data:
				trigger_message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
				action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
				rawcmd = ByteToHex ( message )
				rawcmd = rawcmd.replace(' ', '')
				if re.match(trigger_message, rawcmd):
					logger.debug("Trigger match")
					logger.debug("Message: " + trigger_message + ", Action: " + action)
					action = action.replace("$packettype$", packettype )
					action = action.replace("$subtype$", subtype )
					logger.debug("Execute shell")
					command = Command(action)
					command.run(timeout=config.trigger_timeout)
					if config.trigger_onematch:
						logger.debug("Trigger onematch active, exit trigger")
						return

	# ---------------------------------------
	# Not decoded message
	# ---------------------------------------	
	
	# The packet is not decoded, then print it on the screen
	if decoded == False:
		logger.error("Message not decoded")
		logger.error("Message: " + ByteToHex(message))
		print timestamp + " " + ByteToHex(message)
		print "RFXCMD cannot decode message, see http://code.google.com/p/rfxcmd/wiki/ for more information."

	# decodePackage END
	return

# ----------------------------------------------------------------------------
	
def read_socket():
	"""
	Check socket for messages
	
	Credit: Olivier Djian
	"""

	global messageQueue
	
	if not messageQueue.empty():
		logger.debug("Message received in socket messageQueue")
		message = stripped(messageQueue.get())

		if test_rfx( message ):
		
			# Flush buffer
			serial_param.port.flushOutput()
			logger.debug("SerialPort flush output")
			serial_param.port.flushInput()
			logger.debug("SerialPort flush input")

			timestamp = time.strftime('%Y-%m-%d %H:%M:%S')
			if cmdarg.printout_complete == True:
				print "------------------------------------------------"
				print "Incoming message from socket"
				print "Send\t\t\t= " + ByteToHex( message.decode('hex') )
				print "Date/Time\t\t= " + timestamp
				print "Packet Length\t\t= " + ByteToHex( message.decode('hex')[0] )
				try:
					logger.debug("Decode message to screen")
					decodePacket( message.decode('hex') )
				except KeyError:
					logger.error("Unrecognizable packet")
					print "Error: unrecognizable packet"
			
			logger.debug("Write message to serial port")
			serial_param.port.write( message.decode('hex') )
			
		else:
			logger.error("Invalid message from socket")
			if cmdarg.printout_complete == True:
				print "------------------------------------------------"
				print "Invalid message from socket"

# ----------------------------------------------------------------------------

def test_rfx( message ):
	"""
	Test, filter and verify that the incoming message is valid
	Return true if valid, False if not
	"""
		
	# Remove all invalid characters
	message = stripped(message)
	
	# Remove any whitespaces
	try:
		message = message.replace(' ', '')
	except Exception:
		logger.debug("Error: Removing white spaces")
		return False
	
	# Test the string if it is hex format
	try:
		int(message,16)
	except Exception:
		logger.debug("Error: Packet not hex format")
		return False
	
	# Check that length is even
	if len(message) % 2:
		logger.debug("Error: Packet length not even")
		return False
	
	# Check that first byte is not 00
	if ByteToHex(message.decode('hex')[0]) == "00":
		logger.debug("Error: Packet first byte is 00")
		return False
	
	# Length more than one byte
	if not len(message.decode('hex')) > 1:
		logger.debug("Error: Packet is not longer than one byte")
		return False
	
	# Check if string is the length that it reports to be
	cmd_len = int( ByteToHex( message.decode('hex')[0]),16 )
	if not len(message.decode('hex')) == (cmd_len + 1):
		logger.debug("Error: Packet length is not valid")
		return False

	logger.debug("Test packet: " + message)

	return True
			
# ----------------------------------------------------------------------------

def send_rfx( message ):
	"""
	Decode and send raw message to RFXtrx
	"""
	timestamp = time.strftime('%Y-%m-%d %H:%M:%S')
	
	if cmdarg.printout_complete == True:
		print "------------------------------------------------"
		print "Send\t\t\t= " + ByteToHex( message )
		print "Date/Time\t\t= " + timestamp
		print "Packet Length\t\t= " + ByteToHex( message[0] )
		
		try:
			decodePacket( message )
		except KeyError:
			print "Error: unrecognizable packet"
	
	serial_param.port.write( message )
	time.sleep(1)

# ----------------------------------------------------------------------------

def read_rfx():
	"""
	Read message from RFXtrx and decode the decode the message
	"""
	message = None
	byte = None
	
	try:
		
		try:
			if serial_param.port.inWaiting() != 0:
				timestamp = time.strftime('%Y-%m-%d %H:%M:%S')
				logger.debug('Timestamp: ' + timestamp)
				logger.debug("SerWaiting: " + str(serial_param.port.inWaiting()))
				byte = serial_param.port.read()
				logger.debug('Byte: ' + str(ByteToHex(byte)))
		except IOError, e:
			print("Error: %s" % e)
			logger.error("serial read error: %s" %e)
		
		if byte:
			message = byte + readbytes( ord(byte) )
			logger.debug('Message: ' + str(ByteToHex(message)))
			
			# First byte indicate length of message, must be other than 00
			if ByteToHex(message[0]) <> "00":
			
				# Verify length
				logger.debug('Verify length')
				if (len(message) - 1) == ord(message[0]):
				
					logger.debug('Length OK')
					
					# Whitelist
					if config.whitelist_active:
					
						logger.debug("Check whitelist")
						whitelist_match = False
						for sensor in whitelist.data:
							sensor = sensor.childNodes[0].nodeValue
							logger.debug("Tag: " + sensor)
							rawcmd = ByteToHex ( message )
							rawcmd = rawcmd.replace(' ', '')
							if re.match(sensor, rawcmd):
								logger.debug("Whitelist match")
								whitelist_match = True
								pass
					
						if whitelist_match == False:
							if cmdarg.printout_complete:
								print("Sensor not included in whitelist")
							logger.debug("No match in whitelist, no process")
							return rawcmd
					
					if cmdarg.printout_complete == True:
						print "------------------------------------------------"
						print "Received\t\t= " + ByteToHex( message )
						print "Date/Time\t\t= " + timestamp
						print "Packet Length\t\t= " + ByteToHex( message[0] )
					
					logger.debug('Decode packet')
					try:
						decodePacket( message )
					except KeyError:
						logger.error("Error: unrecognizable packet (" + ByteToHex(message) + ")")
						if cmdarg.printout_complete == True:
							print "Error: unrecognizable packet"

					rawcmd = ByteToHex ( message )
					rawcmd = rawcmd.replace(' ', '')

					return rawcmd
				
				else:
					logger.error('Error: Incoming packet not valid length')
					if cmdarg.printout_complete == True:
						print "------------------------------------------------"
						print "Received\t\t= " + ByteToHex( message )
						print "Incoming packet not valid, waiting for next..."
				
	except OSError, e:
		logger.error('Error in message: ' + str(ByteToHex(message)))
		logger.error('Traceback: ' + traceback.format_exc())
		print "------------------------------------------------"
		print "Received\t\t= " + ByteToHex( message )
		traceback.format_exc()

# ----------------------------------------------------------------------------

def read_config( configFile, configItem):
 	"""
 	Read item from the configuration file
 	"""
 	logger.debug('Open configuration file')
 	logger.debug('File: ' + configFile)
	
	if os.path.exists( configFile ):

		#open the xml file for reading:
		f = open( configFile,'r')
		data = f.read()
		f.close()
	
		# xml parse file data
 		logger.debug('Parse config XML data')
		try:
			dom = minidom.parseString(data)
		except:
			print "Error: problem in the config.xml file, cannot process it"
			logger.debug('Error in config.xml file')
			
		# Get config item
	 	logger.debug('Get the configuration item: ' + configItem)
		
		try:
			xmlTag = dom.getElementsByTagName( configItem )[0].toxml()
			logger.debug('Found: ' + xmlTag)
			xmlData = xmlTag.replace('<' + configItem + '>','').replace('</' + configItem + '>','')
			logger.debug('--> ' + xmlData)
		except:
			logger.debug('The item tag not found in the config file')
			xmlData = ""
			
 		logger.debug('Return')
 		
 	else:
 		logger.error('Error: Config file does not exists')
 		
	return xmlData

# ----------------------------------------------------------------------------

def read_whitelistfile():
 	"""
 	Read whitelist file to list
 	"""
	try:
		xmldoc = minidom.parse( config.whitelist_file )
	except:
		print "Error in " + config.whitelist_file + " file"
		sys.exit(1)

	whitelist.data = xmldoc.documentElement.getElementsByTagName('sensor')

	for sensor in whitelist.data:
		logger.debug("Tags: " + sensor.childNodes[0].nodeValue)
		
# ----------------------------------------------------------------------------

def read_triggerfile():
 	"""
 	Read trigger file to list
 	"""
	try:
		xmldoc = minidom.parse( config.trigger_file )
	except:
		print "Error in " + config.trigger_file + " file"
		sys.exit(1)

	triggerlist.data = xmldoc.documentElement.getElementsByTagName('trigger')

	for trigger in triggerlist.data:
		message = trigger.getElementsByTagName('message')[0].childNodes[0].nodeValue
		action = trigger.getElementsByTagName('action')[0].childNodes[0].nodeValue
		logger.debug("Message: " + message + ", Action: " + action)

# ----------------------------------------------------------------------------

def print_version():
	"""
	Print RFXCMD version, build and date
	"""
	logger.debug("print_version")
 	print "RFXCMD Version: " + __version__
 	print __date__.replace('$', '')
 	logger.debug("Exit 0")
 	sys.exit(0)

# ----------------------------------------------------------------------------

def check_pythonversion():
	"""
	Check python version
	"""
	if sys.hexversion < 0x02060000:
		print "Error: Your Python need to be 2.6 or newer, please upgrade."
		sys.exit(1)

# ----------------------------------------------------------------------------

def option_simulate(indata):
	"""
	Simulate incoming packet, decode and process
	"""

	# Remove all spaces
	for x in string.whitespace:
		indata = indata.replace(x,"")
	
	# Cut into hex chunks
	try:
		message = indata.decode("hex")
	except:
		logger.error("Error: the input data is not valid")
		print "Error: the input data is not valid"
		sys.exit(1)
	
	timestamp = time.strftime('%Y-%m-%d %H:%M:%S')

	# Whitelist
	if config.whitelist_active:
		logger.debug("Check whitelist")
		whitelist_match = False
		for sensor in whitelist.data:
			sensor = sensor.getElementsByTagName('sensor')[0].childNodes[0].nodeValue
			logger.debug("Sensor: " + sensor)
			rawcmd = ByteToHex ( message )
			rawcmd = rawcmd.replace(' ', '')
			if re.match(sensor, rawcmd):
				whitelist_match = True
		
		if whitelist_match == False:
			if cmdarg.printout_complete:
				print("Sensor not included in whitelist")
			logger.debug("No match in whitelist")
			logger.debug("Exit 0")
			sys.exit(0)

	# Printout	
	if cmdarg.printout_complete:
		print "------------------------------------------------"
		print "Received\t\t= " + indata
		print "Date/Time\t\t= " + timestamp
	
	# Verify that the incoming value is hex
	try:
		hexval = int(indata, 16)
	except:
		logger.error("Error: the input data is invalid hex value")
		print "Error: the input data is invalid hex value"
		exit()
				
	# Decode it
	try:
		decodePacket( message )
	except Exception as err:
		logger.error("Error: unrecognizable packet (" + ByteToHex(message) + ")")
		logger.error("Error: %s" %err)
		print "Error: unrecognizable packet"
		
	logger.debug('Exit 0')
	sys.exit(0)

# ----------------------------------------------------------------------------

def option_listen():
	"""
	Listen to RFXtrx device and process data, exit with CTRL+C
	"""
	logger.debug('Action: Listen')

	if config.socketserver:
		serversocket = RFXcmdSocketAdapter(config.sockethost,int(config.socketport))
		if serversocket.netAdapterRegistered:
			logger.debug("Socket interface started")
		else:
			logger.debug("Cannot start socket interface")

	# Flush buffer
	logger.debug('Serialport flush output')
	serial_param.port.flushOutput()
	logger.debug('Serialport flush input')
	serial_param.port.flushInput()

	# Send RESET
	logger.debug('Send rfxcmd_reset (' + rfxcmd.reset + ')')
	serial_param.port.write( rfxcmd.reset.decode('hex') )
	logger.debug('Sleep 1 sec')
	time.sleep(1)

	# Flush buffer
	logger.debug('Serialport flush output')
	serial_param.port.flushOutput()
	logger.debug('Serialport flush input')
	serial_param.port.flushInput()

	# Send STATUS
	logger.debug('Send rfxcmd_status (' + rfxcmd.status + ')')
	serial_param.port.write( rfxcmd.status.decode('hex') )
	logger.debug('Sleep 1 sec')
	time.sleep(1)

	try:
		while 1:
			# let it breath
			time.sleep(0.01)
			
			# Read serial port
			rawcmd = read_rfx()
			if rawcmd:
				logger.debug("Received: " + str(rawcmd))
			
			# Read socket
			if config.socketserver:
				read_socket()
			
	except KeyboardInterrupt:
		logger.debug("Received keyboard interrupt")
		serversocket.netAdapter.shutdown()
		print("\nExit...")
		pass

# ----------------------------------------------------------------------------

def option_getstatus():
	"""
	Get status from RFXtrx device and print on screen
	"""
	# Flush buffer
	serial_param.port.flushOutput()
	serial_param.port.flushInput()

	# Send RESET
	serial_param.port.write(rfxcmd.reset.decode('hex'))
	time.sleep(1)

	# Flush buffer
	serial_param.port.flushOutput()
	serial_param.port.flushInput()

	# Send STATUS
	send_rfx(rfxcmd.status.decode('hex'))
	time.sleep(1)
	read_rfx()

# ----------------------------------------------------------------------------

def option_send():
	"""
	Send command to RFX device
	
	NOTE! Will be depricated in v0.3 and removed in v0.31
	
	"""
	print "SEND action is DEPRICATED, will be removed in version v0.31"
	
	logger.debug('Action: send')

	# Remove any whitespaces	
	cmdarg.rawcmd = cmdarg.rawcmd.replace(' ', '')
	logger.debug('rawcmd: ' + cmdarg.rawcmd)

	# Test the string if it is hex format
	try:
		int(cmdarg.rawcmd,16)
	except ValueError:
		print "Error: invalid rawcmd, not hex format"
		sys.exit(1)		
	
	# Check that first byte is not 00
	if ByteToHex(cmdarg.rawcmd.decode('hex')[0]) == "00":
		print "Error: invalid rawcmd, first byte is zero"
		sys.exit(1)
	
	# Check if string is the length that it reports to be
	cmd_len = int( ByteToHex(cmdarg.rawcmd.decode('hex')[0]),16 )
	if not len(cmdarg.rawcmd.decode('hex')) == (cmd_len + 1):
		print "Error: invalid rawcmd, invalid length"
		sys.exit(1)

	# Flush buffer
	serial_param.port.flushOutput()
	serial_param.port.flushInput()

	# Send RESET
	serial_param.port.write( rfxcmd.reset.decode('hex') )
	time.sleep(1)

	# Flush buffer
	serial_param.port.flushOutput()
	serial_param.port.flushInput()

	if cmdarg.rawcmd:
		timestamp = time.strftime('%Y-%m-%d %H:%M:%S')
		if cmdarg.printout_complete == True:
			print "------------------------------------------------"
			print "Send\t\t\t= " + ByteToHex( cmdarg.rawcmd.decode('hex') )
			print "Date/Time\t\t= " + timestamp
			print "Packet Length\t\t= " + ByteToHex( cmdarg.rawcmd.decode('hex')[0] )
			try:
				decodePacket( cmdarg.rawcmd.decode('hex') )
			except KeyError:
				print "Error: unrecognizable packet"

		serial_param.port.write( cmdarg.rawcmd.decode('hex') )
		time.sleep(1)
		read_rfx()

# ----------------------------------------------------------------------------

def option_bsend():
	"""
	Send command when rfxcmd is running
	Input: none
	
	NOTE! Will be depricated in v0.3 and removed in v0.31
	
	"""
	
	print "BSEND action is DEPRICATED, will be removed in version v0.31"
	
	logger.debug('Action: bsend')
	
	# Remove any whitespaces
	cmdarg.rawcmd = cmdarg.rawcmd.replace(' ', '')
	logger.debug('rawcmd: ' + cmdarg.rawcmd)
	
	# Test the string if it is hex format
	try:
		int(cmdarg.rawcmd,16)
	except ValueError:
		print "Error: invalid rawcmd, not hex format"
		sys.exit(1)		
	
	# Check that first byte is not 00
	if ByteToHex(cmdarg.rawcmd.decode('hex')[0]) == "00":
		print "Error: invalid rawcmd, first byte is zero"
		sys.exit(1)
	
	# Check if string is the length that it reports to be
	cmd_len = int( ByteToHex( cmdarg.rawcmd.decode('hex')[0]),16 )
	if not len(cmdarg.rawcmd.decode('hex')) == (cmd_len + 1):
		print "Error: invalid rawcmd, invalid length"
		sys.exit(1)

	if cmdarg.rawcmd:
		serial_param.port.write(cmdarg.rawcmd.decode('hex'))

# ----------------------------------------------------------------------------

def read_configfile():
	"""
	Read items from the configuration file
	"""
	if os.path.exists( cmdarg.configfile ):

		# ----------------------
		# MySQL
		if (read_config(cmdarg.configfile, "mysql_active") == "yes"):
			config.mysql_active = True
		else:
			config.mysql_active = False
		config.mysql_server = read_config( cmdarg.configfile, "mysql_server")
		config.mysql_database = read_config( cmdarg.configfile, "mysql_database")
		config.mysql_username = read_config( cmdarg.configfile, "mysql_username")
		config.mysql_password = read_config( cmdarg.configfile, "mysql_password")
	
		# ----------------------
		# TRIGGER
		if (read_config( cmdarg.configfile, "trigger_active") == "yes"):
			config.trigger_active = True
		else:
			config.trigger_active = False

		if (read_config( cmdarg.configfile, "trigger_onematch") == "yes"):
			config.trigger_onematch = True
		else:
			config.trigger_onematch = False

		config.trigger_file = read_config( cmdarg.configfile, "trigger_file")
		config.trigger_timeout = read_config( cmdarg.configfile, "trigger_timeout")

		# ----------------------
		# SQLITE
		if (read_config(cmdarg.configfile, "sqlite_active") == "yes"):
			config.sqlite_active = True
		else:
			config.sqlite_active = False		
		config.sqlite_database = read_config(cmdarg.configfile, "sqlite_database")
		config.sqlite_table = read_config(cmdarg.configfile, "sqlite_table")
	
		# ----------------------
		# GRAPHITE
		if (read_config(cmdarg.configfile, "graphite_active") == "yes"):
			config.graphite_active = True
		else:
			config.graphite_active = False
		config.graphite_server = read_config(cmdarg.configfile, "graphite_server")
		config.graphite_port = read_config(cmdarg.configfile, "graphite_port")

		# ----------------------
		# XPL
		if (read_config(cmdarg.configfile, "xpl_active") == "yes"):
			config.xpl_active = True
		else:
			config.xpl_active = False
		config.xpl_host = read_config( cmdarg.configfile, "xpl_host")

		# ----------------------
		# SOCKET SERVER
		if (read_config(cmdarg.configfile, "socketserver") == "yes"):
			config.socketserver = True
		else:
			config.socketserver = False			
		config.sockethost = read_config( cmdarg.configfile, "sockethost")
		config.socketport = read_config( cmdarg.configfile, "socketport")
		logger.debug("SocketServer: " + str(config.socketserver))
		logger.debug("SocketHost: " + str(config.sockethost))
		logger.debug("SocketPort: " + str(config.socketport))
		
		# -----------------------
		# WHITELIST
		if (read_config(cmdarg.configfile, "whitelist_active") == "yes"):
			config.whitelist_active = True
		else:
			config.whitelist_active = False			
		config.whitelist_file = read_config( cmdarg.configfile, "whitelist_file")
		logger.debug("Whitelist_active: " + str(config.whitelist_active))
		logger.debug("Whitelist_file: " + str(config.whitelist_file))
		
	else:

		# config file not found, set default values
		print "Error: Configuration file not found (" + cmdarg.configfile + ")"
		logger.error('Error: Configuration file not found (' + cmdarg.configfile + ')')

# ----------------------------------------------------------------------------

def open_serialport():
	"""
	Open serial port for communication to the RFXtrx device.
	"""

	# Check that serial module is loaded
	try:
		logger.debug("Serial extension version: " + serial.VERSION)
	except:
		print "Error: You need to install Serial extension for Python"
		logger.debug("Error: Serial extension for Python could not be loaded")
		logger.debug("Exit 1")
		sys.exit(1)

	# Check for serial device
	if config.device:
		logger.debug("Device: " + config.device)
	else:
		logger.error('Device name missing')
		print "Serial device is missing"
		logger.debug("Exit 1")
		sys.exit(1)

	# Open serial port
	logger.debug("Open Serialport")
	try:  
		serial_param.port = serial.Serial(config.device, serial_param.rate, timeout=serial_param.timeout)
	except serial.SerialException, e:
		logger.error("Error: Failed to connect on device " + config.device)
		print "Error: Failed to connect on device " + config.device
		print "Error: " + str(e)
		logger.debug("Exit 1")
		sys.exit(1)

	if not serial_param.port.isOpen():
		serial_param.port.open()

# ----------------------------------------------------------------------------

def close_serialport():
	"""
	Close serial port.
	"""

	logger.debug('Close serial port')
	try:
		serial_param.port.close()
		logger.debug('Serial port closed')
	except:
		logger.debug("Failed to close the serial port '" + device + "'")
		print "Error: Failed to close the port " + device
		logger.debug("Exit 1")
		sys.exit(1)

# ----------------------------------------------------------------------------

def logger_init(configfile, name, debug):
	"""

	Init loghandler and logging
	
	Input: 
	
		- configfile = location of the config.xml
		- name	= name
		- debug = True will send log to stdout, False to file
		
	Output:
	
		- Returns logger handler
	
	"""
	program_path = os.path.dirname(os.path.realpath(__file__))
	dom = None
	
	if os.path.exists( os.path.join(program_path, "config.xml") ):

		# Read config file
		f = open(os.path.join(program_path, "config.xml"),'r')
		data = f.read()
		f.close()

		try:
			dom = minidom.parseString(data)
		except:
			print "Error: problem in the config.xml file, cannot process it"

		if dom:
		
			# Get loglevel from config file
			try:
				xmlTag = dom.getElementsByTagName( 'loglevel' )[0].toxml()
				loglevel = xmlTag.replace('<loglevel>','').replace('</loglevel>','')
			except:
				loglevel = "INFO"

			# Get logfile from config file
			try:
				xmlTag = dom.getElementsByTagName( 'logfile' )[0].toxml()
				logfile = xmlTag.replace('<logfile>','').replace('</logfile>','')
			except:
				logfile = os.path.join(program_path, "rfxcmd.log")

			loglevel = loglevel.upper()

			formatter = logging.Formatter(fmt='%(asctime)s - %(levelname)s - %(module)s - %(message)s')
	
			if debug:
				loglevel = "DEBUG"
				handler = logging.StreamHandler()
			else:
				handler = logging.FileHandler(logfile)
							
			handler.setFormatter(formatter)

			logger = logging.getLogger(name)
			logger.setLevel(loglevel)
			logger.addHandler(handler)
			
			return logger


# ----------------------------------------------------------------------------

def main():

	global logger

	# Get directory of the rfxcmd script
	config.program_path = os.path.dirname(os.path.realpath(__file__))

	parser = OptionParser()
	parser.add_option("-d", "--device", action="store", type="string", dest="device", help="The serial device of the RFXCOM, example /dev/ttyUSB0")
	parser.add_option("-a", "--action", action="store", type="string", dest="action", help="Specify which action: LISTEN (default), STATUS, SEND, BSEND")
	parser.add_option("-o", "--config", action="store", type="string", dest="config", help="Specify the configuration file")
	parser.add_option("-x", "--simulate", action="store", type="string", dest="simulate", help="Simulate one incoming data message")
	parser.add_option("-r", "--rawcmd", action="store", type="string", dest="rawcmd", help="Send raw message (need action SEND)")
	parser.add_option("-c", "--csv", action="store_true", dest="csv", default=False, help="Output data in CSV format")
	parser.add_option("-z", "--daemonize", action="store_true", dest="daemon", default=False, help="Daemonize RFXCMD")
	parser.add_option("-p", "--pidfile", action="store", type="string", dest="pidfile", help="PID File location and name")
	parser.add_option("-v", "--version", action="store_true", dest="version", help="Print rfxcmd version information")
	parser.add_option("-D", "--debug", action="store_true", dest="debug", default=False, help="Debug printout on stdout")
	(options, args) = parser.parse_args()

	if options.version:
		print_version()

	# Config file
	if options.config:
		cmdarg.configfile = options.config
	else:
		cmdarg.configfile = os.path.join(config.program_path, "config.xml")

	# Start loghandler
	if options.debug:
		logger = logger_init(cmdarg.configfile,'rfxcmd', True)
	else:
		logger = logger_init(cmdarg.configfile,'rfxcmd', False)
	
	logger.debug("Python version: %s.%s.%s" % sys.version_info[:3])
	logger.debug("RFXCMD Version: " + __version__)
	logger.debug(__date__.replace('$', ''))

	logger.debug("Configfile: " + cmdarg.configfile)
	logger.debug("Read configuration file")
	read_configfile()

	# Whitelist
	if config.whitelist_active:
		logger.debug("Read whitelist file")
		read_whitelistfile()

	# Triggerlist
	if config.trigger_active:
		logger.debug("Read triggerlist file")
		read_triggerfile()

	if options.csv:
		logger.debug("Option: CSV chosen")
		cmdarg.printout_complete = False
		cmdarg.printout_csv = True

	# MYSQL
	if config.mysql_active:
		logger.debug("MySQL active")
		cmdarg.printout_complete = False
		cmdarg.printout_csv = False
		logger.debug("Check MySQL")
		try:
			import MySQLdb
		except ImportError:
			print "Error: You need to install MySQL extension for Python"
			logger.error("Error: Could not find MySQL extension for Python")
			logger.debug("Exit 1")
			sys.exit(1)		

	# SQLITE
	if config.sqlite_active:
		logger.debug("SqLite active")
		cmdarg.printout_complete = False
		cmdarg.printout_csv = False
		logger.debug("Check sqlite3")
		try:
			logger.debug("SQLite3 version: " + sqlite3.sqlite_version)
		except ImportError:
			print "Error: You need to install SQLite extension for Python"
			logger.error("Error: Could not find MySQL extension for Python")
			logger.debug("Exit 1")
			sys.exit(1)
	
	# XPL
	if config.xpl_active:
		logger.debug("XPL active")
		cmdarg.printout_complete = False

	# GRAPHITE
	if config.graphite_active:
		logger.debug("Graphite active")
		cmdarg.printout_complete = False

	if cmdarg.printout_complete == True:
		if not options.daemon:
			print "RFXCMD Version " + __version__

	# Serial device
	if options.device:
		config.device = options.device
	else:
		config.device = None

	# Deamon
	if options.daemon:
		logger.debug("Option: Daemon chosen")
		logger.debug("Check PID file")
		
		if options.pidfile:
			cmdarg.pidfile = options.pidfile
			cmdarg.createpid = True
			logger.debug("PID file '" + cmdarg.pidfile + "'")
		
			if os.path.exists(cmdarg.pidfile):
				print("PID file '" + cmdarg.pidfile + "' already exists. Exiting.")
				logger.debug("PID file '" + cmdarg.pidfile + "' already exists.")
				logger.debug("Exit 1")
				sys.exit(1)
			else:
				logger.debug("PID file does not exists")

		else:
			print("You need to set the --pidfile parameter at the startup")
			logger.debug("Command argument --pidfile missing")
			logger.debug("Exit 1")
			sys.exit(1)

		logger.debug("Check platform")
		if sys.platform == 'win32':
			print "Daemonize not supported under Windows. Exiting."
			logger.debug("Daemonize not supported under Windows.")
			logger.debug("Exit 1")
			sys.exit(1)
		else:
			logger.debug("Platform: " + sys.platform)
			
			try:
				logger.debug("Write PID file")
				file(cmdarg.pidfile, 'w').write("pid\n")
			except IOError, e:
				logger.debug("Unable to write PID file: %s [%d]" % (e.strerror, e.errno))
				raise SystemExit("Unable to write PID file: %s [%d]" % (e.strerror, e.errno))

			logger.debug("Deactivate screen printouts")
			cmdarg.printout_complete = False

			logger.debug("Start daemon")
			daemonize()

	# Action
	if options.action:
		cmdarg.action = options.action.lower()
		if not (cmdarg.action == "listen" or cmdarg.action == "send" or
			cmdarg.action == "bsend" or cmdarg.action == "status"):
			logger.error("Error: Invalid action")
			parser.error('Invalid action')
	else:
		cmdarg.action = "listen"

	logger.debug("Action chosen: " + cmdarg.action)

	# Rawcmd
	if cmdarg.action == "send" or cmdarg.action == "bsend":
		cmdarg.rawcmd = options.rawcmd
		if not cmdarg.rawcmd:
			print "Error: You need to specify message to send with -r <rawcmd>. Exiting."
			logger.error("Error: You need to specify message to send with -r <rawcmd>")
			logger.debug("Exit 1")
			sys.exit(1)
	
		logger.debug("Rawcmd: " + cmdarg.rawcmd)

	if options.simulate:
		option_simulate( options.simulate )

	open_serialport()

	if cmdarg.action == "listen":
		option_listen()

	if cmdarg.action == "status":
		option_getstatus()

	if cmdarg.action == "send":
		option_send()

	if cmdarg.action == "bsend":
		option_bsend()

	close_serialport()

	logger.debug("Exit 0")
	sys.exit(0)
	
# ------------------------------------------------------------------------------

if __name__ == '__main__':

	# Init shutdown handler
	signal.signal(signal.SIGINT, handler)
	signal.signal(signal.SIGTERM, handler)

	# Init objects
	config = config_data()
	cmdarg = cmdarg_data()
	rfx = lib.rfx_sensors.rfx_data()
	rfxcmd = rfxcmd_data()
	serial_param = serial_data()
	
	# Triggerlist
	triggerlist = trigger_data()
	
	# Whitelist
	whitelist = whitelist_data()

	# Check python version
	check_pythonversion()
	
	main()

# ------------------------------------------------------------------------------
# END
# ------------------------------------------------------------------------------
