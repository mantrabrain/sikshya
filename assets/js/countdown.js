;
(function ($, window, document) {

    function init($element) {
        var currentTime = new Date();
        var dataSeconds = $element.data('timer');
        var dataTimerEnd = $element.data('timer-end');
        var dataLabels = JSON.parse(decodeURIComponent($element.data('timer-labels')));

        // var dataServerTimeValue = new Date().setSeconds(currentTime.getSeconds() + 3600)
        var dataServerTime = $element.data('server-time');

        var defaultSeconds = 120;
        var clientTime = parseInt(((dataServerTime * 1000) - Date.parse(currentTime)) / 1000);


        var seconds = function () {
            if (dataSeconds) {
                if (dataServerTime) {
                    return dataSeconds + clientTime;
                }
                return dataSeconds;
            }
            else {
                return defaultSeconds;
            }
        };

//        console.log(dataSeconds);

        var title = [];
        var titles = [];

        var headlines = {
            "year": ["Year", "Years"],
            "mounth": ["Month", "Months"],
            "weeks": ["Week", "Weeks"],
            "days": ["Day", "Days"],
            "hours": ["Hour", "Hours"],
            "minutes": ["Minute", "Minutes"],
            "seconds": ["Second", "Seconds"]
        };

        (function setDataTitle() {
            for (var label in headlines) {
                if (dataLabels[label]) {
                    headlines[label] = dataLabels[label];
                }
                title.push(headlines[label][0]);
                titles.push(headlines[label][1]);
            }
        })();

        $element.countdown({
            until: new Date(currentTime.setSeconds(currentTime.getSeconds() + seconds())),
            format: 'D:H:M:S',
            labels: titles,
            labels1: title,
            onExpiry: function () {
                if (dataTimerEnd === 'reload') {
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                }
            }
        });

    }


    $(document).ready(function () {
        var container = '.js-countdown-timer';
        var title = '.js-countdown-title-date';

        var currentDate = new Date();
        var formatDate = {
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            timezone: 'UTC'
        };

        $(container).each(function (i, elem) {
            init($(this));
            $(title).text(new Date(currentDate.setSeconds($(this).data('timer'))).toLocaleString("us", formatDate));
        });

    });



})(jQuery, window, document);