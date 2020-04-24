jQuery(document).ready(function ($) {
  document.create_cb_bookings_gantt_chart = function(identifier) {
    $('#cb-bookings-gantt-chart-wrapper').remove();

    var $el = $(identifier);
    var url = $el.data('url');
    var item_id = $el.data('item_id');
    var date_start = $el.data('date_start');
    var date_end = $el.data('date_end');

    console.log('data: ', item_id, date_start, date_end);

    var data = {
      //'nonce': this.settings.nonce,
			'action': 'cb_bookings_get_gantt_chart_data',
      'item_id': item_id,
      'date_start': date_start,
      'date_end': date_end
		};

    console.log('fetch location data from: ', url);

    var wrapper_dimensions = {
      width: 600,
      height: 320
    };

    var wrapper_pos = calculate_chart_wrapper_position($el, wrapper_dimensions);

    var $canvas_wrapper = $('<div id="cb-bookings-gantt-chart-wrapper" style="background-color: #fff; border: 1px solid #666666; position: absolute; z-index: 1000; left: ' + wrapper_pos.left + 'px; top: ' + wrapper_pos.top + 'px; width: ' + wrapper_dimensions.width + 'px; height: ' + wrapper_dimensions.height + 'px;"></div>')
    var $head = $('<div style="width: 100%; height: 20px; text-align: right;"></div>');
    $canvas_wrapper.append($head);
    var $close = $('<span style="padding-right: 10px; font-size: 18px; line-height: 28px; cursor: pointer;">X</span>');
    $head.append($close)
    var $canvas = $('<canvas style="width: 600px; height: 300px;" id="cb-bookings-gantt-chart"></canvas>');
    $canvas_wrapper.append($canvas);
    $('body').append($canvas_wrapper);

    $close.click(function() {
      $canvas_wrapper.remove();
    });

    jQuery.post(url, data, function(response) {
      booking_data = response.bookings;
      console.log('booking data: ', booking_data);

      var labels = [];
      var chart_data = [];
      var backgroundColor = [];

      var backgroundColors = {
         'blocking': '#ff6666',
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

      var ctx = document.getElementById('cb-bookings-gantt-chart');

      Chart.Tooltip.positioners.cursor = function(chartElements, coordinates) {
        return coordinates;
      };

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
          animation: false,
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
            },
            mode: 'label',
            position: 'cursor',
            intersect: true,
            caretSize: 0
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

  function calculate_chart_wrapper_position($el, wrapper_dimensions) {
    var element_offset = $el.offset();
    console.log('element_offset: ', element_offset);

    var document_dimensions = {
      width: $(document).width(),
      height: $(document).height()
    }

    console.log('document_dimensions: ', document_dimensions);

    var factor_x = 1;
    var factor_y = -1;

    var wrapper_offset_x = 5;
    var wrapper_offset_y = wrapper_dimensions.height / 2;

    var horizontal_space = document_dimensions.width - element_offset.left;
    console.log('horizontal_space: ', horizontal_space);

    if(horizontal_space < wrapper_dimensions.width) {
      factor_x = -1;
      wrapper_offset_x += wrapper_dimensions.width;
    }
    else {
      wrapper_offset_x += $el.outerWidth();
    }

    var wrapper_pos = {
      left: element_offset.left + factor_x * wrapper_offset_x,
      top: element_offset.top + factor_y * wrapper_offset_y
    };

    return wrapper_pos;
  }
});
