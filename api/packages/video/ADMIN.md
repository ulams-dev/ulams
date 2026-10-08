# Admin panel documentation

This package is used to convert video to the HTTP Live Streaming (HLS) Format when creating a video [topic type](https://github.com/EscolaLMS/topic-types).
To create a video topic, you can upload a new file in *mp4*, *ogg* or *webm* format or select a previously uploaded file from the file browser.

Once the video is uploaded, the conversion process begins, and you can track its progress using the progress bar displayed above the video.

![Video form](https://github.com/EscolaLMS/Video/assets/59456825/fd685b1e-a0d2-4b37-aaa3-f39b2181490a)

You can display information about the processing status by displaying the *JSON Metadata* value.

![JSON metadata](https://github.com/EscolaLMS/Video/assets/59456825/bcea1d5a-bbe7-4033-82e0-d389dc7bf1f3)

### Configuration 

You can configure this package by navigating to the *Settings* tab and selecting the *package video* tab.

![Settings](https://github.com/EscolaLMS/Video/assets/59456825/1fddf358-6118-438f-8c32-845f297a2cab)

- `enable` - Enables or disables video processing. When it is disabled then the url to the original file is returned.
- `non_strict_value` - When this setting is enabled, the url to the original file is returned until the conversion process is finished. This allows the topic to be made available to students earlier.

![bitrates](https://github.com/EscolaLMS/Video/assets/59456825/d55c00f2-3329-45b1-9404-05e43a348a3d)

- `bitrates` - This setting controls the quality and resolution. The key takes an array of values.
  - `kiloBitrates` - parameter determines the video quality
  - `scale` - parameter controls the video resolution. Adjusting this parameter may cause video distortion (you can omit this parameter)
