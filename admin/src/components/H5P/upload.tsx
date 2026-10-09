import SecureUpload from '@/components/SecureUpload';
import { H5P_UPLOAD_URL } from '@/services/ulams/h5p';
import React from 'react';

/** Uploads a .h5p package to the H5P service (POST /h5p/contents/upload). */
export const UploadH5P: React.FC<{
  onSuccess: (response: API.DefaultResponse<API.H5PUploadResult>) => void;
  onError: (message?: string) => void;
  hideLabel?: boolean;
}> = ({ onSuccess, onError, hideLabel }) => {
  return (
    <SecureUpload<API.H5PUploadResult>
      url={H5P_UPLOAD_URL}
      name="h5p_file"
      accept=".h5p"
      hideLabel={hideLabel}
      onChange={(info) => {
        if (info.file.status === 'done') {
          if (info.file.response) {
            onSuccess(info.file.response);
          }
        }
        if (info.file.status === 'error') {
          const response = info.file.response as { message?: string } | undefined;
          onError(response?.message);
        }
      }}
    />
  );
};

export default UploadH5P;
