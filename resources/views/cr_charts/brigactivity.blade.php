@extends('cr_charts.layout')

@section('content')
    <form id="form">
        <label for="from">Since</label>
        <input type="date" required id="from"/>
        <input type="text" required placeholder="Brigade name" id="name"/>
        <input type="submit" value="Add"/>
    </form>
    <canvas id="chart"></canvas>
@endsection

@section('after-scripts-end')
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"
            integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script type="text/javascript">

        var colors = [
            '#ff0029',
            '#377eb8',
            '#66a61e',
            '#984ea3',
            '#00d2d5',
            '#bdea5b',
            '#fe2ea7',
            '#fbafa1',
            '#c6e21e'
        ];

        $('#form').submit(e => {
            e.preventDefault();
            var brigName = $('#name').val();
            fetch('https://cr-api.vi0.de/q/brigActivity?from=' + encodeURIComponent($('#from').val()) + '&name=' + encodeURIComponent(brigName)).then(function (res) {
                if (!res.ok) {
                    alert("Could not load Brig data");
                    throw Error("Could not load Brig data");
                }
                return res.json();
            }).then(jsonData => {
                let datasetTemplate = getDataSetTemplate(colors.shift());

                var chart = window.myLine;
                //    chart.data.labels.push(label);

                chart.data.datasets.push(Object.assign({}, datasetTemplate, {
                    label: brigName + '_Kills',
                    data: transformData(jsonData.kills),
                }));
                chart.data.datasets.push(Object.assign({}, datasetTemplate, {
                    label: brigName + '_Players',
                    data: transformData(jsonData.players),
                }));

                chart.update();
            });
        });

        var config = {
            type: 'line',
            data: {},
            options: {
                elements: {
                    point: {radius: 1}
                },
                responsive: true,
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false
                        },
                        type: 'time',
                        time: {
                            unit: 'hour'
                        },
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Servertime hour'
                        },
                        ticks: {

                            major: {
                                fontStyle: 'bold',
                                fontColor: '#FF0000'
                            }
                        }
                    }],
                    yAxes: [{
                        display: true,

                        scaleLabel: {

                            display: true

                        }
                    }]
                }
            }
        };

        function getDataSetTemplate(color) {
            return {
                fill: false,
                borderColor: color,
                backgroundColor: color,
                borderWidth: 1,
                //cubicInterpolationMode:'monotone',
                //steppedLine: true,
                //showLine: false,
                pointRadius: 3
            }
        }

        drawChart();

        function drawChart(allData) {
            var ctx = document.getElementById('chart').getContext('2d');
            window.myLine = new Chart(ctx, config);
        }

        function transformData(data) {
            return Object.keys(data).map(h => {
                let hDate = new Date();
                hDate.setHours(h);
                hDate.setMinutes(30);
                hDate.setSeconds(0);
                return {
                    x: hDate,
                    y: data[h]
                };
            })
        }
    </script>
@endsection
