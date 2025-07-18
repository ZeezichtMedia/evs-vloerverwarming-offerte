<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>{{subject}}</title>
    <style type="text/css">{{css}}</style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td>
                <h2>{{greeting}}</h2>
                <p>{{intro}}</p>
                
                <h3>{{your_data}}</h3>
                <table width="100%" cellpadding="5" cellspacing="0" border="0">
                    <tr><td><strong>{{heating_type_label}}</strong></td><td>{{heating_type_value}}</td></tr>
                    <tr><td><strong>{{housing_type_label}}</strong></td><td>{{housing_type_value}}</td></tr>
                    <tr><td><strong>{{floor_type_label}}</strong></td><td>{{floor_type_value}}</td></tr>
                    <tr><td><strong>{{area_label}}</strong></td><td>{{area_value}}</td></tr>
                    <tr><td><strong>{{heat_source_label}}</strong></td><td>{{heat_source_value}}</td></tr>
                    <tr><td><strong>{{sealing_label}}</strong></td><td>{{sealing_value}}</td></tr>
                </table>

                <h3>{{quote_details}}</h3>
                <table width="100%" cellpadding="5" cellspacing="0" border="0">
                    <tr><td><strong>{{drilling_price_label}}</strong></td><td class="price">{{drilling_price_value}}</td></tr>
                    <tr><td><strong>{{sealing_price_label}}</strong></td><td class="price">{{sealing_price_value}}</td></tr>
                    <tr><td><strong>{{total_price_label}}</strong></td><td class="price">{{total_price_value}}</td></tr>
                </table>

                <h3>{{next_steps}}</h3>
                <p>{{next_steps_body}}</p>

                <div class="footer">
                    <p>{{regards}}</p>
                    <p><strong>{{team}}</strong></p>
                    <p style="margin-top:15px; font-size:14px; color:#666;">{{contact_info}}</p>
                    <p style="font-size:12px; color:#999;">{{company_details}}</p>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
