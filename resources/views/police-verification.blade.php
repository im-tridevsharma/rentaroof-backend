<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Police Verification</title>
</head>

<body>
    <table width="100%" style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #000000;">
        <tr>
            <th colspan="3" style="border-top:1px solid lightgray;border-bottom:1px solid lightgray;">
                <h2 style="color:firebrick;">Police Verification Data</h2>
            </th>
        </tr>
        <tr>
            <th colspan="3" style="height:50px; text-align:left;">
                <h3>Landlord Details</h3>
            </th>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Name</td>
            <td style="height:50px;text-align:left;">{{ $landlord->first }} {{ $landlord->last }}</td>
            <td rowspan="4">
                <div style="
                background-repeat:no-repeat;
                    background-position:center;background-size:cover;background-image:url({{ $landlord->profile_pic }});
                     width:150px; height:150px;border:1px solid lighrgay;">
                </div>
            </td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Mobile</td>
            <td style="height:50px;text-align:left;">{{ $landlord->mobile }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Email</td>
            <td style="height:50px;text-align:left;">{{ $landlord->email }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Gender</td>
            <td style="height:50px;text-align:left;">{{ $landlord->gender }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Present Address</td>
            <td style="height:50px;text-align:left;">{{ $lkyc->present_address }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Permanent Address</td>
            <td style="height:50px;text-align:left;">{{ $lkyc->permanent_address }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Document Type</td>
            <td style="height:50px;text-align:left;">{{ $lkyc->document_type }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Document Number</td>
            <td style="height:50px;text-align:left;">{{ $lkyc->document_number }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Document</td>
            <td style="height:50px;text-align:left;">
                <div
                    style="width:300px;height:120px;
                background-repeat:no-repeat;
                    background-position:center;background-size:cover;background-image:url({{ $lkyc->document_upload }});background-color:white;">
                </div>
            </td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Signature</td>
            <td style="height:50px;text-align:left;">
                <div style="width:300px;height:120px;
                background-position:center;background-size:cover;
                background-repeat:no-repeat;
                    background-image:url({{ $landlord->signature }});background-color:white;">
                </div>
            </td>
            <td></td>
        </tr>
    </table>
    <table width="100%" style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #000000;">
        <tr>
            <th colspan="3" style="height:50px; border-top:1px solid lightgray; text-align:left;">
                <h3>Tenant Details</h3>
            </th>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Name</td>
            <td style="height:50px;text-align:left;">{{ $tenant->first }} {{ $tenant->last }}</td>
            <td rowspan="4">
                <div style="
                background-repeat:no-repeat;
                    background-position:center;background-size:cover;background-image:url({{ $tenant->profile_pic }});
                     width:150px; height:150px;border:1px solid lighrgay;">
                </div>
            </td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Mobile</td>
            <td style="height:50px;text-align:left;">{{ $tenant->mobile }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Email</td>
            <td style="height:50px;text-align:left;">{{ $tenant->email }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Gender</td>
            <td style="height:50px;text-align:left;">{{ $tenant->gender }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Present Address</td>
            <td style="height:50px;text-align:left;">{{ $tkyc->present_address }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Permanent Address</td>
            <td style="height:50px;text-align:left;">{{ $tkyc->permanent_address }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Document Type</td>
            <td style="height:50px;text-align:left;">{{ $tkyc->document_type }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Document Number</td>
            <td style="height:50px;text-align:left;">{{ $tkyc->document_number }}</td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Document</td>
            <td style="height:50px;text-align:left;">
                <div style="width:300px;height:120px;
                background-position:center;background-size:cover;
                background-repeat:no-repeat;
                    background-image:url({{ $tkyc->document_upload }});background-color:white;">
                </div>
            </td>
            <td></td>
        </tr>
        <tr>
            <td style="height:50px;text-align:left;">Signature</td>
            <td style="height:50px;text-align:left;">
                <div style="width:300px;height:120px;
                background-position:center;background-size:cover;
                background-repeat:no-repeat;
                    background-image:url({{ $tenant->signature }});background-color:white;">
                </div>
            </td>
            <td></td>
        </tr>
    </table>
</body>

</html>
