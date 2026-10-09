import React, { useEffect, useState } from "react";
import { Gallery, Item } from "react-photoswipe-gallery";
import { API } from "@ulams/sdk";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import "../../../utils/photoswipe.css";
import styles from "./ImagePlayer.module.css";
import { ResponsiveImage } from "../../organisms/ResponsiveImage/ResponsiveImage";

interface ImagePlayerProps extends ExtendableStyledComponent {
  topic: API.TopicImage;
  onLoad: () => void;
}

export const ImagePlayer: React.FC<ImagePlayerProps> = ({
  topic,
  onLoad,
  className = "",
}) => {
  const [imgSrc, setImgSrc] = useState("");

  useEffect(() => {
    onLoad();
  }, []);

  return (
    <>
      <Gallery
        options={{
          arrowPrev: false,
          arrowNext: false,
          imageClickAction: "zoom",
          initialZoomLevel: "fit",
          secondaryZoomLevel: 2,
          maxZoomLevel: 3,
        }}
      >
        <div className={`${styles.root} ulams-component ${className}`}>
          <Item
            original={imgSrc}
            width={topic.topicable.width}
            height={topic.topicable.height}
            alt={`LMS Image ${topic.topicable.id}`}
          >
            {({ ref, open }) => (
              <ResponsiveImage
                path={topic.topicable.value}
                onClick={open}
                onLoad={(e) => setImgSrc(e.currentTarget.currentSrc)}
                ref={ref as React.MutableRefObject<HTMLImageElement>}
                srcSizes={[500, 750, 1000]}
              />
            )}
          </Item>
        </div>
      </Gallery>
    </>
  );
};

export default ImagePlayer;
