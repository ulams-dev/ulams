# Jitsi

Jitsi integration



## What does it do
This package introduce just a facade that you can use to generate parameters for jitsi player

## Installing
- `composer require ulams/jitsi`
- Setup environmental config to point to Jitsi service - use either `env` file or [Settings package](https://github.com/EscolaLMS/Settings) (settings should be visible in the settings endpoint)

```php
return [
    'host' => env('JITSI_HOST', 'meet-stage.ulams.app'),
    'app_id' => env('JITSI_APP_ID', 'meet-id'),
    'secret' => env('JITSI_APP_SECRET', 'secret'),
    'package_status' => 'enabled',
];
```

If `app_id` or `secret` service will skip `JWT` token generation.

Once you provide the above you can generate parameters, example from tinker

```php
\Ulams\Jitsi\Facades\Jitsi::getChannelData(App\Models\User::find(1), "czesc ziomku", true, ['logoImageUrl'=>'https://ulams.pl/_next/image?url=%2Fimages%2Flogo-ulams.svg&w=3840&q=75'])
```

would generate some thing like

```php
[
     "data" => [
       "domain" => "meet-stage.ulams.app",
       "roomName" => "czescZiomku",
       "configOverwrite" => [
         "logoImageUrl" => "https://ulams.pl/_next/image?url=%2Fimages%2Flogo-ulams.svg&w=3840&q=75",
       ],
       "interfaceConfigOverwrite" => [
       ],
       "userInfo" => [
         "id" => 1,
         "name" => "Osman Kanu",
         "displayName" => "Osman Kanu",
         "email" => "student@ulams.com",
         "moderator" => true,
       ],
       "jwt" => "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJtZWV0LWlkIiwiYXVkIjoibWVldC1pZCIsInN1YiI6Im1lZXQtc3RhZ2UuZXNjb2xhbG1zLmNvbSIsImV4cCI6MTY0MzY1OTM1NCwicm9vbSI6ImN6ZXNjWmlvbWt1IiwidXNlciI6eyJpZCI6MSwibmFtZSI6Ik9zbWFuIEthbnUiLCJkaXNwbGF5TmFtZSI6Ik9zbWFuIEthbnUiLCJlbWFpbCI6InN0dWRlbnRAZXNjb2xhLWxtcy5jb20iLCJtb2RlcmF0b3IiOmZhbHNlfX0.xnFV-Kk63c3YRADzkSQLz6FP71yfEUO7Q53isFGkv_U",
     ],
     "host" => "meet-stage.ulams.app",
     "url" => "https://meet-stage.ulams.app/czescZiomku?jwt=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJtZWV0LWlkIiwiYXVkIjoibWVldC1pZCIsInN1YiI6Im1lZXQtc3RhZ2UuZXNjb2xhbG1zLmNvbSIsImV4cCI6MTY0MzY1OTM1NCwicm9vbSI6ImN6ZXNjWmlvbWt1IiwidXNlciI6eyJpZCI6MSwibmFtZSI6Ik9zbWFuIEthbnUiLCJkaXNwbGF5TmFtZSI6Ik9zbWFuIEthbnUiLCJlbWFpbCI6InN0dWRlbnRAZXNjb2xhLWxtcy5jb20iLCJtb2RlcmF0b3IiOmZhbHNlfX0.xnFV-Kk63c3YRADzkSQLz6FP71yfEUO7Q53isFGkv_U",
   ]
```

pass this object into endpoint that generates jitsi call. You should definitely [read the manual before](https://jitsi.github.io/handbook/docs/dev-guide/dev-guide-web-sdk).

Example

```tsx
import React from "react";
import JitsiMeeting from "@jitsi/web-sdk/lib/components/JitsiMeeting";
import type {
  IJitsiMeetExternalApi,
  IJitsiMeetingProps,
} from "@jitsi/web-sdk/lib/types";

const dataFromEndpoint = {
  domain: "meet-stage.ulams.app",
  roomName: "czescZiomku",
  configOverwrite: {},
  interfaceConfigOverwrite: {},
  userInfo: {
    displayName: "Osman Kanu",
    email: "student@ulams.com",
  },
  jwt: "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJtZWV0LWlkIiwiYXVkIjoibWVldC1pZCIsInN1YiI6Im1lZXQtc3RhZ2UuZXNjb2xhbG1zLmNvbSIsImV4cCI6MTY0MzY1OTM1NCwicm9vbSI6ImN6ZXNjWmlvbWt1IiwidXNlciI6eyJpZCI6MSwibmFtZSI6Ik9zbWFuIEthbnUiLCJkaXNwbGF5TmFtZSI6Ik9zbWFuIEthbnUiLCJlbWFpbCI6InN0dWRlbnRAZXNjb2xhLWxtcy5jb20iLCJtb2RlcmF0b3IiOmZhbHNlfX0.xnFV-Kk63c3YRADzkSQLz6FP71yfEUO7Q53isFGkv_U",
};

const data: IJitsiMeetingProps = {
  ...dataFromEndpoint,
  onApiReady: (api) => console.log("api ready", api),
};

function App() {
  return (
    <div className="App">
      <JitsiMeeting {...data} />
    </div>
  );
}

export default App;
```

## Tests

Run `./vendor/bin/phpunit --filter 'Ulams\\Jitsi\\Tests'` to run tests. See [tests](tests) folder as it's quite good staring point as documentation appendix.

