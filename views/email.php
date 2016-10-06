<html>
    <head>
        <style>
            h1 {
                font-size: 14px;
            }
        </style>
    </head>
    <body>
    <h1>What's new:</h1>
    {% if (count($activity)==0) %}
    Nothing is new.
    {% else %}
    <ul>
        {% foreach($activity as $item) %}
        <li>
            <strong>{{$item['user_name']}}</strong> &mdash;
            {{$item['activity']}}
            {% if ($item['post_title'] && !empty($item['post_title']) && ($item['post_title']!='Auto Draft')) %}
                &mdash; <a href="http://shh.mi.lk/wp/wp-admin/post.php?post={{$item['post_id']}}&action=edit">{{$item['post_title']}}</a>
            {% endif %}
        </li>
        {% endforeach %}
    </ul>
    {% endif %}
    <p>To unsubscribe, <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">click here</a>.</p>
    </body>
</html>