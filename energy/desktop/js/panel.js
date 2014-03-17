
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

$(function() {
    if (isset(datas)) {
        if (isset(datas.history)) {
            if (isset(datas.history.power)) {
                console.log(datas.history.power);
                drawSimpleGraph('div_graphGlobalPower', datas.history.power, 'Puissance');
            }
        }
        if (isset(datas.details)) {
            drawStackGraph('div_graphDetailPowerByObject', datas.details);
            var pourcentage = [];
            for (var i in datas.details) {
                var value = (datas.details[i].data.real.consumption / datas.real.consumption) * 100;
                if (value != 0) {
                    var info = [];
                    info.push(datas.details[i].name);
                    info.push(value);
                    pourcentage.push(info);
                }
            }
            drawPieChart('div_graphDetailConsumptionByObject', pourcentage);
        }

    }
});

function drawPieChart(_el, _data, _title) {
    new Highcharts.Chart({
        chart: {
            renderTo: _el,
            plotBackgroundColor: null,
            plotBorderWidth: null,
            plotShadow: false,
            height: 300
        },
        title: {
            text: ''
        },
        tooltip: {
            pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
        },
        credits: {
            text: 'Copyright Jeedom',
            href: '',
        },
        plotOptions: {
            pie: {
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: {
                    enabled: true,
                    color: '#000000',
                    connectorColor: '#000000',
                    format: '<b>{point.name}</b>: {point.percentage:.1f} %'
                }
            }
        },
        series: [{
                type: 'pie',
                name: 'Browser share',
                data: _data
            }]
    });
}

function drawStackGraph(_el, _data) {

    var series = [];
    for (var i in _data) {
        if (isset(_data[i].data.history.power) && _data[i].data.history.power.length > 0) {

            var serie = {
                type: 'area',
                name: _data[i].name,
                data: _data[i].data.history.power,
                tooltip: {
                    valueDecimals: 2
                }
            };
            series.push(serie);
        }
    }

    if (!$.mobile) {
        var legend = {
            enabled: true,
            borderColor: 'black',
            borderWidth: 2,
            shadow: true
        };
    } else {
        var legend = {};
    }



    new Highcharts.StockChart({
        chart: {
            zoomType: 'x',
            renderTo: _el,
            height: 300
        },
        plotOptions: {
            area: {
                stacking: 'normal',
                lineColor: '#666666',
                lineWidth: 1,
                marker: {
                    lineWidth: 1,
                    lineColor: '#666666'
                }
            }
        },
        credits: {
            text: 'Copyright Jeedom',
            href: '',
        },
        navigator: {
            enabled: false
        },
        rangeSelector: {
            buttons: [{
                    type: 'minute',
                    count: 30,
                    text: '30m'
                }, {
                    type: 'hour',
                    count: 1,
                    text: 'H'
                }, {
                    type: 'day',
                    count: 1,
                    text: 'J'
                }, {
                    type: 'week',
                    count: 1,
                    text: 'S'
                }, {
                    type: 'month',
                    count: 1,
                    text: 'M'
                }, {
                    type: 'year',
                    count: 1,
                    text: 'A'
                }, {
                    type: 'all',
                    count: 1,
                    text: 'Tous'
                }],
            selected: 6,
            inputEnabled: false
        },
        legend: legend,
        tooltip: {
            pointFormat: '<span style="color:{series.color}">{series.name}</span>: <b>{point.y}</b><br/>',
            valueDecimals: 2,
        },
        yAxis: [{
                format: '{value}',
                showEmpty: false,
                showLastLabel: true,
                labels: {
                    align: 'right',
                    x: -5
                }
            }, {
                opposite: true,
                format: '{value}',
                showEmpty: false,
                gridLineWidth: 0,
                labels: {
                    align: 'left',
                    x: -5
                }
            }],
        xAxis: {
            type: 'datetime',
            ordinal: false,
        },
        scrollbar: {
            barBackgroundColor: 'gray',
            barBorderRadius: 7,
            barBorderWidth: 0,
            buttonBackgroundColor: 'gray',
            buttonBorderWidth: 0,
            buttonBorderRadius: 7,
            trackBackgroundColor: 'none', trackBorderWidth: 1,
            trackBorderRadius: 8,
            trackBorderColor: '#CCC'
        },
        series: series
    });

}

function drawSimpleGraph(_el, _data, _name) {
    if (!$.mobile) {
        var legend = {
            enabled: true,
            borderColor: 'black',
            borderWidth: 2,
            shadow: true
        };
    } else {
        var legend = {};
    }


    var series = {
        type: 'line',
        name: _name,
        data: _data,
        tooltip: {
            valueDecimals: 2
        }
    };


    new Highcharts.StockChart({
        chart: {
            zoomType: 'x',
            renderTo: _el,
            height: 300
        },
        plotOptions: {
            series: {
                stacking: 'normal'
            }
        },
        credits: {
            text: 'Copyright Jeedom',
            href: '',
        },
        navigator: {
            enabled: false
        },
        rangeSelector: {
            buttons: [{
                    type: 'minute',
                    count: 30,
                    text: '30m'
                }, {
                    type: 'hour',
                    count: 1,
                    text: 'H'
                }, {
                    type: 'day',
                    count: 1,
                    text: 'J'
                }, {
                    type: 'week',
                    count: 1,
                    text: 'S'
                }, {
                    type: 'month',
                    count: 1,
                    text: 'M'
                }, {
                    type: 'year',
                    count: 1,
                    text: 'A'
                }, {
                    type: 'all',
                    count: 1,
                    text: 'Tous'
                }],
            selected: 6,
            inputEnabled: false
        },
        legend: legend,
        tooltip: {
            pointFormat: '<span style="color:{series.color}">{series.name}</span>: <b>{point.y}</b><br/>',
            valueDecimals: 2,
        },
        yAxis: [{
                format: '{value}',
                showEmpty: false,
                showLastLabel: true,
                labels: {
                    align: 'right',
                    x: -5
                }
            }, {
                opposite: true,
                format: '{value}',
                showEmpty: false,
                gridLineWidth: 0,
                labels: {
                    align: 'left',
                    x: -5
                }
            }],
        xAxis: {
            type: 'datetime',
            ordinal: false,
        },
        scrollbar: {
            barBackgroundColor: 'gray',
            barBorderRadius: 7,
            barBorderWidth: 0,
            buttonBackgroundColor: 'gray',
            buttonBorderWidth: 0,
            buttonBorderRadius: 7,
            trackBackgroundColor: 'none', trackBorderWidth: 1,
            trackBorderRadius: 8,
            trackBorderColor: '#CCC'
        },
        series: [series]
    });
}

