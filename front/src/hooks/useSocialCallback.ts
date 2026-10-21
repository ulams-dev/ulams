import { useContext, useEffect, useRef } from "react";
import { UlamsContext } from "@ulams/sdk/react/context";
import { exchangeSocialCode } from "@ulams/sdk/services/auth";
import { readSocialCallback, takeVerifier } from "@ulams/sdk/utils/socialLogin";

/**
 * Finishes a social login on the page the API redirected to (ADR 0121): the one-time `code` in the
 * URL is removed from the address bar and the history at once, then redeemed with the verifier this
 * browser kept, and the access token goes straight into the session. Runs once per code.
 *
 * @param onDone called with `ok` true when a session was created, false for any failure or when the
 *   provider gave no e-mail address (the sign-up then needs the "complete" step of the API)
 */
export function useSocialCallback(search: string, onDone: (ok: boolean, error?: string | null) => void): boolean {
  const { apiUrl, socialAuthorize } = useContext(UlamsContext);
  const { code, error } = readSocialCallback(search);
  const handled = useRef<string | null>(null);

  useEffect(() => {
    const key = code ?? (error ? `error:${error}` : null);
    if (!key || handled.current === key) return;
    handled.current = key;

    // the code is single use, but there is no reason to keep it in the address bar or the history
    window.history.replaceState(window.history.state, "", window.location.pathname + window.location.hash.replace(/\?.*$/, ""));

    if (!code) {
      onDone(false, error);
      return;
    }
    const verifier = takeVerifier();
    if (!verifier) {
      onDone(false, "invalid");
      return;
    }
    exchangeSocialCode(apiUrl, { code, verifier })
      .then((response) => {
        const data = "data" in response ? response.data : null;
        if (data && data.complete) {
          socialAuthorize(data.token);
          onDone(true);
        } else {
          onDone(false, "email_required");
        }
      })
      .catch(() => onDone(false, "invalid"));
  }, [code, error, apiUrl, socialAuthorize, onDone]);

  return Boolean(code);
}
