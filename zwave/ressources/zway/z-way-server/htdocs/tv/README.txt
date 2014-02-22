Для всех deviceId, отличных от zwayControllerNodeId и 255:
     Если instanceId == 0 и количество инстансов устройства больше 1, игнорировать инстанс

     Если commandClassId == 0x25 и у устройства существует commandClassId == 0x26, игногировать инстанс

     Если commandClassId == 0x25, выбрать виджет SwitchBinary и получать данные от data.level (перечисление: 0, 255)

     Если commandClassId == 0x26, выбрать виджет SwitchMultilevel и получать данные от data.level (диапазон: 0-100)

     Если commandClassId == 0x30, выбрать виджет SensorBinary и получать данные от data.level (булевое значение)

     Если commandClassId == 0x31, создать количество виджетов SensorMultilevel, равное количеству цифровых ключей в data:
          Виджет типизируется по data[key].sensorTypeString
          Виджет получает данные от data[key].value (точное значение)

     Если commandClassId == 0x32, создать количество виджетов Meter, равное количеству цифровых ключей в data:
          Виджет типизируется по data[key].sensorType
          Виджет получает данные от data[key].value (точное значение)

     Если commandClassId == 0x62, создать виджетов Lock, и получать данные от data.mode:
        0x00: 'Open';
        0x10: 'Open from inside';
        0x20: 'Open from outside';
        0xff: 'Closed';
