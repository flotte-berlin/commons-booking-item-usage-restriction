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

    //if there is a previously generated chart, dispose it
    if(window.cb_bookings_gantt_chart) {
      window.cb_bookings_gantt_chart.dispose();
    }

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
      height: 100
    };

    var wrapper_pos = calculate_chart_wrapper_position($el, wrapper_dimensions);

    var $loading = $('<span class="cb-bookings-gantt-chart-loading dashicons dashicons-update-alt"></span>');
    var $canvas_wrapper = $('<div id="cb-bookings-gantt-chart-wrapper" data-uuid="' + uuid + '" style=""></div>');
    $canvas_wrapper.append($loading);
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
    var $canvas = $('<div id="cb-bookings-gantt-chart"></div>');
    $canvas_wrapper.append($canvas);

    $close.click(function() {
      window.cb_bookings_gantt_chart.dispose();
      $canvas_wrapper.remove();
    });

    jQuery.post(url, data, function(response) {
      $loading.remove();

      booking_data = response.bookings;
      console.log('booking data: ', booking_data);

      var chart_data = [];

      var canvas_element = document.getElementById('cb-bookings-gantt-chart');

      var booking_type_labels = {
        'blocking': 'blockierend',
        'confirmed': 'bestätigt',
        'aborted': 'spät storniert',
        'overbooking': 'überbuchend',
        'blocked': 'blockiert',
        'canceled': 'storniert'
      }

      var bookings_by_id = {};

      Object.keys(booking_data).forEach((booking_group, i) => {

        booking_data[booking_group].forEach((booking) => {
          var color_value = getComputedStyle(canvas_element).getPropertyValue('--bar-status-bg-' + booking.type); //fetched from CSS variables
          //console.log('color_value: ', color_value, typeof color_value);

          bookings_by_id[booking.id] = booking;

          chart_data.push(
            {
              //data
              type: booking_type_labels[booking.type],
              category: booking_group,
              bookingId: booking.id,
              date_start: booking.date_start,
              date_end: booking.date_end,
              user_name: booking.user.name,
              user_role: booking.user.role,
              comment: booking.comment,

              //styling
              fill: am4core.color(color_value.trim()),
            }
          );

        });
      });

      console.log('chart data: ', chart_data);

      var chart = am4core.create("cb-bookings-gantt-chart", am4charts.XYChart);
      window.cb_bookings_gantt_chart = chart;
      chart.paddingLeft = 30;
      chart.paddingRight = 30;
      chart.dateFormatter.inputDateFormat = "yyyy-MM-dd HH:mm:ss";

      var title = chart.titles.create();
      title.marginTop = -10;
      title.text = "Buchungen für " + response.item.name;

      chart.data = chart_data;

      var cell_size = 30;
      chart.events.on("datavalidated", function(ev) {

        // Get objects of interest
        var chart = ev.target;
        var categoryAxis = chart.yAxes.getIndex(0);

        var rows_count = 0;
        Object.keys(booking_data).forEach(function(category) {
          if(booking_data[category].length > 0) {
            rows_count++;
          }
        });

        // calculate how we need to adjust chart height
        var adjustHeight = rows_count * cell_size - categoryAxis.pixelHeight;

        // get current chart height
        var targetHeight = chart.pixelHeight + adjustHeight;

        // set it on chart's container
        chart.svgContainer.htmlElement.style.height = targetHeight + "px";

        //set wrapper size and position
        wrapper_dimensions = {
          width: 600,
          height: targetHeight + 20 //including head
        };
        wrapper_pos = calculate_chart_wrapper_position($el, wrapper_dimensions);

        //adjust position of amcharts attribution
        var $attribution_dom_path = $(":contains(Chart created using amCharts library)").parent();
        var $attribution = $($attribution_dom_path[$attribution_dom_path.length - 1]);
        var attribution_translate_y = targetHeight - 21;
        $attribution.attr('transform', 'translate(0, ' + attribution_translate_y + ')');

        //set wrapper styles
        $canvas_wrapper.css({
          left: wrapper_pos.left,
          top: wrapper_pos.top,
          width: wrapper_dimensions.width,
          height: wrapper_dimensions.height
        });
      });

      var categoryAxis = chart.yAxes.push(new am4charts.CategoryAxis());
      categoryAxis.dataFields.category = "category";
      categoryAxis.renderer.grid.template.location = 0;
      categoryAxis.renderer.inversed = true;
      categoryAxis.renderer.labels.template.disabled = true;
      categoryAxis.renderer.grid.template.disabled = true;

      var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
      dateAxis.dateFormatter.dateFormat = "dd.MM.yyyy";

      dateAxis.baseInterval = { count: 24 * 60, timeUnit: "minute" };
      dateAxis.min = new Date(response.ticks.min).getTime();
      dateAxis.max = new Date(response.ticks.max).getTime();
      dateAxis.strictMinMax = true;
      dateAxis.renderer.tooltipLocation = 0;
      dateAxis.dateFormats.setKey("day", "dd.MM.");
      dateAxis.periodChangeDateFormats.setKey("day", "dd.MM.");

      dateAxis.renderer.minGridDistance = 70;
      dateAxis.gridIntervals.setAll([
        { timeUnit: "day", count: 1},
        { timeUnit: "day", count: 2},
        { timeUnit: "day", count: 7},
      ]);

      var series1 = chart.series.push(new am4charts.ColumnSeries());
      series1.tooltip.pointerOrientation = "vertical";
      series1.tooltip.getFillFromObject = false;
      series1.tooltip.background.fill = am4core.color("#222222");
      series1.columns.template.tooltipText = `[bold]{user_name} ({user_role})[/]
        {openDateX} - {dateX} (Id: {bookingId})
        ({type})
        {comment}`;

      series1.dataFields.openDateX = "date_start";
      series1.dataFields.dateX = "date_end";
      series1.dataFields.categoryY = "category";
      series1.columns.template.propertyFields.fill = "fill"; // get color from data
      series1.columns.template.strokeWidth = 0;
      series1.columns.template.column.adapter.add("cornerRadiusTopLeft", cornerRadiusLeft);
      series1.columns.template.column.adapter.add("cornerRadiusTopRight", cornerRadiusRight);
      series1.columns.template.column.adapter.add("cornerRadiusBottomLeft", cornerRadiusLeft);
      series1.columns.template.column.adapter.add("cornerRadiusBottomRight", cornerRadiusRight);

      function cornerRadiusLeft(radius, item) {
        return new Date(item.dataItem.dates.openDateX).getTime() < dateAxis.min ? 0 : 5;
      }

      function cornerRadiusRight(radius, item) {
        return new Date(item.dataItem.dates.dateX).getTime() > dateAxis.max ? 0 : 5;
      }

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
      top: element_offset.top + factor_y * wrapper_offset_y + $el.height() / 2
    };

    return wrapper_pos;
  }
});
