<?php
/**
 * @var $v
 */
?>
    <style>

        #map-container {
            z-index: 20;
        }

        #map-frame {
            display: none;
            width: 100%;
            height: 100%;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            right: 0;
            align-items: center;
            justify-content: center;

            /*transform: translate(-50%, -50%);*/
        }

        #background {
            background-color: rgba(255,255,255,.7);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10;
        }

        #map-loader {
            display: none;
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.8) url("<?php echo plugins_url( '/img/loader.gif', __FILE__ )?>") center center no-repeat;
            z-index: 1000;
        }
    </style>

    <script>
        (function ($) {
            $(document).ready(function () {
                $('body').append('<div id="map-frame"><div id="map-container"><div id="map"><div id="map-loader"></div></div></div>' +
                    '<div id="background"></div></div>');

                $('#background').click(function () {
                    $('#map-frame').css('display', 'none');
                })
            })
        })(jQuery);
    </script>
<?php ?>
