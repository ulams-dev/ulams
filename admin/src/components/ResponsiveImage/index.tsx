import { resolveImage } from '@/services/ulams/imageService';
import type { PresetName } from '@ulams/images';
import type { ImageProps } from 'antd';
import { Image } from 'antd';
import React, { useMemo, useState } from 'react';

interface ResponsiveImageProps extends ImageProps {
  /** Storage path; the legacy PHP resizer (`php` mode, the default) needs it. */
  path: string;
  /** Public URL of the file; `cloudflare` and `noop` modes need it (files without one use the PHP resizer). */
  url?: string | null;
  size?: number;
  srcSizes?: number[];
  /** Width list and quality used in `cloudflare` mode (`@ulams/sdk/images` presets). */
  preset?: PresetName;
  alt?: string;
}

const ResponsiveImage: React.FC<ResponsiveImageProps> = ({
  path,
  url,
  size,
  srcSizes,
  preset,
  alt = 'LMS Image',
  onError,
  ...props
}) => {
  const resolved = useMemo(
    () => resolveImage({ path, url }, { size, srcSizes, preset }),
    // the mode is a page-load setting; the inputs are what can change
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [path, url, size, srcSizes?.join(','), preset],
  );
  // a failed transformation falls back to the original file once; a failed original is left to the browser
  const [failedSrc, setFailedSrc] = useState<string>();
  const fallback = failedSrc === resolved.src && resolved.original !== resolved.src;

  return (
    <div className="ulams-image">
      <Image
        src={fallback ? resolved.original : resolved.src}
        srcSet={fallback ? undefined : resolved.srcSet}
        alt={alt}
        onError={(event) => {
          if (!fallback && resolved.original && resolved.original !== resolved.src) {
            setFailedSrc(resolved.src);
          }
          onError?.(event);
        }}
        {...props}
      />
    </div>
  );
};

export default ResponsiveImage;
