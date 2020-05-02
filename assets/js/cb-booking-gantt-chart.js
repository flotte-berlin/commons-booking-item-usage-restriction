jQuery(document).ready(function ($) {
  document.init_cb_bookings_gantt_chart = function(identifier) {
    var $el = $(identifier);
    var uuid = $el.data('uuid');

    var $opened_chart_wrapper = $('#cb-bookings-gantt-chart-wrapper');

    if($opened_chart_wrapper.length) {
      opened_chart_uuid = $opened_chart_wrapper.data('uuid');

      if(uuid != opened_chart_uuid) {
        $('#cb-bookings-gantt-chart-wrapper').remove();

        create_cb_bookings_gantt_chart($el);
      }
    }
    else {
      create_cb_bookings_gantt_chart($el);
    }

  };

  function create_cb_bookings_gantt_chart($el) {

    var url = $el.data('url');
    var item_id = $el.data('item_id');
    var date_start = $el.data('date_start');
    var date_end = $el.data('date_end');
    var uuid = $el.data('uuid');

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

    var $canvas_wrapper = $('<div id="cb-bookings-gantt-chart-wrapper" data-uuid="' + uuid + '" style=""></div>');
    $('body').append($canvas_wrapper);

    wrapper_dimensions = {
      width: $canvas_wrapper.outerWidth(),
      height: $canvas_wrapper.outerHeight()
    };

    $canvas_wrapper.css({
      left: wrapper_pos.left,
      top: wrapper_pos.top
    })

    var $head = $('<div class="cb-bookings-gantt-chart-head"></div>');
    $canvas_wrapper.append($head);
    var $close = $('<span class="cb-bookings-gantt-chart-close">X</span>');
    $head.append($close)
    var $canvas = $('<canvas id="cb-bookings-gantt-chart"></canvas>');
    $canvas_wrapper.append($canvas);

    $close.click(function() {
      $canvas_wrapper.remove();
    });

    jQuery.post(url, data, function(response) {
      booking_data = response.bookings;
      console.log('booking data: ', booking_data);

      var labels = [];
      var chart_data = [];
      var backgroundColor = [];

      var canvas_element = document.getElementById('cb-bookings-gantt-chart');

      var booking_type_labels = {
        'blocking': 'blockierend',
        'confirmed': 'bestätigt',
        'aborted': 'spät storniert',
        'overbooking': 'überbuchend',
        'blocked': 'blockiert',
        'canceled': 'storniert'
      }

      var booking_types = [];
      var bookings_by_id = {};

      Object.keys(booking_type_labels).forEach((label, i) => {

        booking_data[label].forEach((booking, i) => {
          labels.push(booking.id);
          bookings_by_id[booking.id] = booking;
          chart_data.push([new Date(booking.date_start), new Date(booking.date_end)]);
          backgroundColor.push(getComputedStyle(canvas_element).getPropertyValue('--bar-status-bg-' + label)); //fetched from CSS variables
          booking_types.push(booking_type_labels[label]);
        });
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
            text: 'Buchungen für ' + response.item.name
          },
          tooltips: {
            callbacks: {
              title: function(tooltipItems, data) {
                var booking = bookings_by_id[tooltipItems[0]['value']];
                return booking.user.name + ' (' + booking.user.role  + ')';
              },
              beforeLabel: function(tooltipItem, data) {
                var result  = ''
                var booking = bookings_by_id[tooltipItem['value']];
                var date_start = moment(booking.date_start).format('DD.MM.YY');
                var date_end = moment(booking.date_end).format('DD.MM.YY');

                if(date_start == date_end) {
                  result = date_start;
                }
                else {
                  result = date_start + ' - ' + date_end;
                }

                return result + ' (Id: ' + tooltipItem['value'] + ')';

              },
              label: function(tooltipItem, data) {
                return booking_types[tooltipItem['index']];
              },
              afterLabel: function(tooltipItem, data) {
                var booking = bookings_by_id[tooltipItem['value']];
                return booking.comment;
              },
            },
            mode: 'label',
            position: 'cursor',
            intersect: true,
            caretSize: 0,
            displayColors: false
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
              ticks: ticks,
              offset: true
            }],
            yAxes: [{
                display: false
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
