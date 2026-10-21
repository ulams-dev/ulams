# Admin panel documentation

This package is used to convert video to the HTTP Live Streaming (HLS) Format when creating a video [topic type](https://github.com/EscolaLMS/topic-types).
To create a video topic, you can upload a new file in *mp4*, *ogg* or *webm* format or select a previously uploaded file from the file browser.

Once the video is uploaded, the conversion process begins, and you can track its progress using the progress bar displayed above the video.

![Video form](docs/img/admin-01.png)

You can display information about the processing status by displaying the *JSON Metadata* value.

![JSON metadata](docs/img/admin-02.png)

### Configuration 

You can configure this package by navigating to the *Settings* tab and selecting the *package video* tab.

![Settings](docs/img/admin-03.png)

- `enable` - Enables or disables video processing. When it is disabled then the url to the original file is returned.
- `non_strict_value` - When this setting is enabled, the url to the original file is returned until the conversion process is finished. This allows the topic to be made available to students earlier.

![bitrates](docs/img/admin-04.png)

- `bitrates` - This setting controls the quality and resolution. The key takes an array of values.
  - `kiloBitrates` - parameter determines the video quality
  - `scale` - parameter controls the video resolution. Adjusting this parameter may cause video distortion (you can omit this parameter)
