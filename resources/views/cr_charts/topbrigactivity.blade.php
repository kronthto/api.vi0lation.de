@extends('cr_charts.layout')

@section('content')
    <form id="form">
        <label for="from">Since:</label>
        <input type="date" required id="from"/>
        <br/>
        <label>Hours:</label>
        <input type="number" required placeholder="Start hour" id="from_hour" min="0" max="23"/>
        <input type="number" required placeholder="End hour" id="to_hour" min="0" max="23"/>
        <br/>
        <input type="submit" value="Show"/>
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
            '#c6e21e',
            '#000000'
        ];
        // TODO: dotted, slice 20

        $('#form').submit(e => {
            e.preventDefault();
            fetch('https://cr-api.vi0.de/q/topbrigActivity?from=' + encodeURIComponent($('#from').val())
                + '&fromHour=' + encodeURIComponent($('#from_hour').val())
                + '&toHour=' + encodeURIComponent($('#to_hour').val())).then(function (res) {
                if (!res.ok) {
                    alert("Could not load data");
                    throw Error("Could not load data");
                }
                return res.json();
            }).then(jsonData => {

                jsonData = jsonData.slice(0,20);






                var chart = window.myLine;
                //    chart.data.labels.push(label);

                chart.destroy();
                config.data = {datasets: []};

                jsonData.forEach((brig,i) => {

                    let color = colors[i % colors.length];
                    let dotted = i >= colors.length;

                    let datasetTemplate = getDataSetTemplate(color, dotted);
                    config.data.datasets.push(Object.assign({}, datasetTemplate, {
                        label: brig.brig,
                        data: transformData(brig),
                    }));
                });


                drawChart();
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

                            labelString: 'Player number',
                            display: true

                        }
                    }]
                }
            }
        };

        function getDataSetTemplate(color, dotted) {
            return {
                fill: false,
                borderColor: color,
                backgroundColor: color,
                borderWidth: 1,
                //cubicInterpolationMode:'monotone',
                //steppedLine: true,
                //showLine: false,
                borderDash: dotted ? [10,5] : [],
                pointRadius: 3
            }
        }

        drawChart();

        function drawChart() {
            var ctx = document.getElementById('chart').getContext('2d');
            window.myLine = new Chart(ctx, config);
        }

        function transformData(data) {
            return Object.keys(data)
                .filter(h => h.length < 3)
                .map(h => {
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
