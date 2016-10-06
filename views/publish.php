<?php
$lastpublished=get_option('ilab-publish-last-published');

$tz = get_option('timezone_string');

if (!empty($tz))
    date_default_timezone_set(get_option('timezone_string'));

if (!$lastpublished || empty($lastpublished))
    $lastpublished='Never';
else
    $lastpublished=date('n/j/Y g:i a',strtotime($lastpublished));
?>
<style xmlns="http://www.w3.org/1999/html">
    .current-status dfn {
        font-weight: bold;
        width: 160px;
        display: inline-block;
        text-align:right;
        margin-right:10px;
    }
</style>
<p class="wrap">
<h1>Publish Live</h1>
<p id="ajax-result">
<ul class="current-status">
    <li>
        <dfn>Current Version</dfn>
        {{get_option('ilab-publish-current-version')}}
    </li>
    <li>
        <dfn>Current Theme Version</dfn>
        {{get_option('ilab-publish-current-theme-version')}}
    </li>
    <li>
        <dfn>Last Publish Date</dfn>
        {{$lastpublished}}
    </li>
</ul>
</p>
    <p>Click the button below to publish the current content to the live site.</p>
    <div style="margin-bottom: 10px"><input type="checkbox" id="publish_theme">Update theme build</div>
    <a href="#" class="ilab-ajax button">Publish to Live Site</a><div id="publish-spinner" class="spinner" style="float:none"></div>

</div>
<script>
    (function($){
        $(document).ready(function(){
            var publishing=false;

           $('.ilab-ajax').on('click',function(e){
               e.preventDefault();

               if (publishing)
                return false;

               publishing=true;

               var button=$('#publish-spinner');
               button.addClass('is-active');
               var publishTheme=($('#publish_theme').attr('checked')=='checked') ? 1 : 0;

               var data={
                   action: 'ilab_publish_live',
                   theme: publishTheme
               };

               $.post(ajaxurl,data,function(response){
                   button.removeClass('is-active');
                   $('#ajax-result').append(response);
                   publishing=false;
               });
               return false;
           });

        });
    })(jQuery);
</script>