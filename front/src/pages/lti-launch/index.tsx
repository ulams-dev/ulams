import React, { useContext, useEffect, useState } from "react";
import { useHistory, useLocation } from "react-router-dom";
import { UlamsContext } from "@ulams/sdk/react";
import { Spin } from "@ulams/components/components/atoms/Spin/Spin";

/**
 * Landing page of an LTI launch from another LMS (api/packages/lti, tool side). The API
 * redirects here with a one-time code, which is exchanged for an API token; the learner then
 * lands in the course.
 */
const LtiLaunchPage: React.FC = () => {
  const { apiUrl, socialAuthorize } = useContext(UlamsContext);
  const { search } = useLocation();
  const { replace } = useHistory();
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const params = new URLSearchParams(search);
    const code = params.get("code");
    if (!code) {
      setError("This link is incomplete. Open the activity in your LMS again.");
      return;
    }
    const controller = new AbortController();
    fetch(`${apiUrl}/api/lti/tool/exchange`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ code }),
      signal: controller.signal,
    })
      .then(async (response) => {
        const body = await response.json().catch(() => null);
        if (!response.ok || !body?.data?.token) {
          throw new Error(
            body?.message ??
              "This sign-in link has expired. Open the activity in your LMS again."
          );
        }
        socialAuthorize(body.data.token);
        replace(`/course/${body.data.course_id}`);
      })
      .catch((e: Error) => {
        if (!controller.signal.aborted) {
          setError(e.message);
        }
      });
    return () => controller.abort();
  }, [apiUrl, search, socialAuthorize, replace]);

  return (
    <main style={{ padding: 24 }}>
      {error ? (
        <p role="alert">{error}</p>
      ) : (
        <div role="status" aria-live="polite">
          <Spin />
          <span className="sr-only">Signing you in…</span>
        </div>
      )}
    </main>
  );
};

export default LtiLaunchPage;
