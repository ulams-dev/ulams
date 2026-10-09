/**
 * Stripe 3-D Secure: when the bank requires authentication the API answers `POST /api/cart/pay`
 * with a `redirect_url` (the hosted authentication page). The front then leaves the SPA to
 * complete it; without one the payment succeeded right away.
 */
export function stripeRedirectUrl(response: unknown): string | undefined {
  const data = (response as { data?: { redirect_url?: unknown } } | null | undefined)?.data;
  const url = data?.redirect_url;
  return typeof url === "string" && url.length > 0 ? url : undefined;
}
