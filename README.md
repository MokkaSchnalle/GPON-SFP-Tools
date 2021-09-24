# GPON-SFP-Tools

`gen_huawei.php` is a small PHP script that will provide an easy to use form for managing GPON authentication data on Huawei MA5671A GPON SFP modules with stock firmware using env variable manipulation.

The variable is stored in Base64 on the module. This script converts data to hex, will apply the new changes and then converts back to the original Base64.

## Features
- Change GPON S/N
- Change GPON PLOAM password
- Change GPON MAC address
- Preview new data
- Test a variable to check its auth values
