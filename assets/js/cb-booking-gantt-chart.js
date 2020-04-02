jQuery(document).ready(function ($) {
  document.create_cb_bookings_gantt_chart = function(identifier) {
      var url = $(identifier).data('url');
      var item_id = $(identifier).data('item_id');
      var date_start = $(identifier).data('date_start');
      var date_end = $(identifier).data('date_end');

      console.log('data: ', item_id, date_start, date_end);

      var data = {
      //'nonce': this.settings.nonce,
			'action': 'cb_bookings_get_gantt_chart_data',
      'item_id': item_id,
      'date_start': date_start,
      'date_end': date_end
		};

    console.log('fetch location data from: ', url);

    jQuery.post(url, data, function(response) {
      booking_data = response.bookings;
      console.log('booking data: ', booking_data);

      var labels = [];
      var chart_data = [];
      var backgroundColor = [];

      var backgroundColors = {
         'blocking': '#ee7400',
         'user': '#7fc600',
         'overbooking': '#589ad7'
      };

      booking_types = [];

      booking_data['blocking'].forEach((booking, i) => {
        labels.push(booking.id);
        chart_data.push([new Date(booking.date_start), new Date(booking.date_end)]);
        backgroundColor.push(backgroundColors['blocking']);
        booking_types.push('Totalausfall');
      });

      booking_data['user'].forEach((booking, i) => {
        labels.push(booking.id);
        chart_data.push([new Date(booking.date_start), new Date(booking.date_end)]);
        backgroundColor.push(backgroundColors['user']);
        booking_types.push('normale Buchung');
      });

      booking_data['overbooking'].forEach((booking, i) => {
        labels.push(booking.id);
        chart_data.push([new Date(booking.date_start), new Date(booking.date_end)]);
        backgroundColor.push(backgroundColors['overbooking']);
        booking_types.push('Überbuchung');
      });

      console.log('chart data: ', chart_data);
      console.log('labels: ', labels);

      var ticks = {
        min: new Date(response.ticks.min).getTime(),
        max: new Date(response.ticks.max).getTime() + 1000
      }

      $('body').append('<canvas style="background-color: #fff; position: absolute; left: 250px; top: 250px; width: 600px; height: 300px;" id="cb-bookings-gantt-chart"></canvas>');

      var ctx = document.getElementById('cb-bookings-gantt-chart');

      // create chart
      var myBarChart = new Chart(ctx, {
        type: 'horizontalBar',
        data: {
          labels: labels,
          datasets: [
            {
              //label: 'Buchungen',
              data: chart_data,
              backgroundColor: backgroundColor,
              borderWidth: 1
            }
          ]
        },
        options: {
          title: {
            display: true,
            text: 'Bestätigte Buchungen'
          },
          tooltips: {
            callbacks: {
              title: function(tooltipItem, data) {
                console.log(tooltipItem);
                console.log(data);
                return booking_types[tooltipItem[0]['index']];
              }
            }
          },
          legend: { display: false },
          responsive: false,
          scales: {
            xAxes: [{
              type: 'time',
              time: {
                unit: 'day',
                unitStepSize: 1,
                displayFormats: {
                  day: 'DD.MM.'
                }
              },
              ticks: ticks
            }],
            yAxes: [{
              gridLines: {
                display: false ,
                //color: "#FFFFFF"
              }
            }]
          }
        }
      });
  });
  }
});
