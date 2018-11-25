@extends('cr_charts.layout')

@section('content')
    <canvas id="chart"></canvas>
@endsection

@section('after-scripts-end')
    <script type="text/javascript">

        var urlParams = new URLSearchParams(window.location.search);

        var backdays = urlParams.get('backdays') || 7;
        var groupMinutes = urlParams.get('groupMinutes') || 120;

        var famehistoryPromise = fetch('/api/chromerivals/fame-activity?days=' + backdays + '&groupMinutes=' + groupMinutes).then(function (res) {
            return res.json();
        });

        var config = {
            type: 'line',
            data: {},
            options: {
                elements: {
                    point: {radius: 0}
                },
                responsive: true,
                title: {
                    display: true,
                    text: 'Aggregated fame per ' + groupMinutes + 'min-intervals'
                },
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false
                        },
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                hour: 'YYYY-MM-DD hh:00'
                            }
                        },
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Date'
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

                            display: true,
                            labelString: 'Kills'

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

        var seriesColorCoding = {
            'BCU': '#F5B930',
            'ANI': '#00FFFF',
            'I': '#0078ff',
            'M': '#ff0000',
            'B': '#ffb400',
            'A': '#17ef00'
        };

        var dataKey = urlParams.get('mode') === 'gear' ? 'byGear' : 'byNation';
        famehistoryPromise.then(drawChart);

        function drawChart(allData) {
            config.data.datasets = buildDatasets(allData);

            var ctx = document.getElementById('chart').getContext('2d');
            window.myLine = new Chart(ctx, config);
        }

        function buildDatasets(data) {

            var serieses = {};

            for (var ts in data[dataKey]) {
                var dt = new Date(ts * 1000);
                for (var series in data[dataKey][ts]) {
                    if (!(series in serieses)) {
                        serieses[series] = []
                    }
                    serieses[series].push({
                        'x': dt,
                        'y': data[dataKey][ts][series]
                    })
                }
            }

            return Object.keys(serieses).map(function (seriesKey) {
                return Object.assign(getDataSetTemplate(seriesColorCoding[seriesKey]), {
                    label: seriesKey,
                    data: serieses[seriesKey]
                });
            });
        }
    </script>
@endsection
