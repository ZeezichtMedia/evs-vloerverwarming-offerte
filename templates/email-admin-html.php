<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>{{subject}}</title>
    <style type="text/css">{{css}}</style>
</head>
<body>
    <h2>{{subject}}</h2>
    
    <h3>{{customer_details}}</h3>
    <table>
        <tr><th align="left">{{name_label}}</th><td>{{name_value}}</td></tr>
        <tr><th align="left">{{email_label}}</th><td>{{email_value}}</td></tr>
        <tr><th align="left">{{phone_label}}</th><td>{{phone_value}}</td></tr>
    </table>

    <h3>{{address_details}}</h3>
    <table>
        <tr><th align="left">{{address_label}}</th><td>{{address_value}}</td></tr>
        <tr><th align="left">{{postcode_label}}</th><td>{{postcode_value}}</td></tr>
        <tr><th align="left">{{city_label}}</th><td>{{city_value}}</td></tr>
    </table>

    <h3>{{project_details}}</h3>
    <table>
        <tr><th align="left">{{heating_type_label}}</th><td>{{heating_type_value}}</td></tr>
        <tr><th align="left">{{housing_type_label}}</th><td>{{housing_type_value}}</td></tr>
        <tr><th align="left">{{floor_type_label}}</th><td>{{floor_type_value}}</td></tr>
        <tr><th align="left">{{area_label}}</th><td>{{area_value}}</td></tr>
        <tr><th align="left">{{heat_source_label}}</th><td>{{heat_source_value}}</td></tr>
        <tr><th align="left">{{sealing_label}}</th><td>{{sealing_value}}</td></tr>
        <tr><th align="left">{{date_label}}</th><td>{{date_value}}</td></tr>
    </table>

    <h3>{{notes_label}}</h3>
    <p>{{notes_value}}</p>

    <h3>{{quote_details}}</h3>
    <p class="price">{{total_price_label}} {{total_price_value}}</p>

    <hr>
    <p>{{quote_id_text}}</p>
</body>
</html>
