# Youtube

Package Youtube integration 


## What does it do

This package is used for creating Youtube livestream for Webinar in Ulams.

## Installing

- `composer require ulams/youtube`
- configure integration in https://console.cloud.google.com/

## Configuration in console cloude youtube
Login in console cloud google and create new project
![Create new project in google console](docs/create_new_project_in_google_console.png "Create new project in google console")
After created project got to the interfaces api and enable YouTube Data API v3
![Enable interface Youtube data api](docs/enable_yt_data_api.png "Enable interface Youtube data api")

![Copy Login data from youtube api](docs/login_data.png "Copy Login data from youtube api")
Go to Login Data and create Api key and OAuth 2.0 client IDs and enter data for variables: 
 - `services.youtube.client_id`
 - `services.youtube.client_secret`
 - `services.youtube.api_key`
 - `services.youtube.redirect_url`
 
After entered data, you must generate refresh token. 
If you generated refresh token with api from endpoints: 
  - `api/admin/g-token/generate POST {"email": "email"} AUTHORIZE` and opened generated url and follow the instructions
  - After action upper yt generated refresh token for variable `services.youtube.refresh_token`
Or you can generated refresh token manual and enter for variable `services.youtube.refresh_token`
  
## Tests

Run `./vendor/bin/phpunit --filter=Youtube` to run tests. See [tests](tests) folder as it's quite good staring point as documentation appendix.

Test details [![codecov](https://codecov.io/gh/Ulams/Youtube/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/Ulams/Youtube) [![phpunit](https://github.com/EscolaLMS/Youtube/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Youtube/actions/workflows/test.yml)
