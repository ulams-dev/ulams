# 0013. Ship the same SPA as iOS/Android apps with Capacitor

- Status: Accepted (retroactive)
- Date: 2024-05-15

## Context and Problem Statement

In 2024 the front gained subscription products, onboarding and mobile layouts and was
submitted for iOS review ("ios revieew", 2024-04-09). App stores require in-app purchases for
digital subscriptions and native push notifications.

## Considered Options

Not recorded.

## Decision Outcome

The web app is wrapped with Capacitor 5 (`@capacitor/core`, `android`, `ios`,
`status-bar`, `filesystem`, `local-notifications`; `@capacitor/assets` for icons/splash from
`resources/`). Mobile-only code paths use `isMobilePlatform`; in-app subscriptions use
`@revenuecat/purchases-capacitor` (SDK types for RevenueCat products: sdk `d49fb8da`);
push notifications use Firebase (`@capacitor-firebase/messaging`, `firebase`,
`useFirebase` hook) configured through `VITE_APP_FIREBASE_*` and `VITE_APP_IOS/ANDROID_APIKEY`.
The native `android/` and `ios/` projects are not committed to this repository.

### Consequences

- Good: one codebase for web and store apps.
- Bad: payment logic forks between Stripe/P24 (web) and RevenueCat (mobile).
- Bad: native projects live outside version control here, so mobile builds are not
  reproducible from this repo alone. Inferred from `.gitignore` changes in `9a7995d9`.

## Evidence

- `fad0faaa` 2024-04-09 "ios revieew"; `7763e158` 2024-04-18 "ios improvments"
- `51edfb36` 2024-05-15 "Add packages for capacitior, revenuecat and firebase..." - `front/package.json`, `front/src/App.tsx`, `CoursesDetailsSidebar/Buttons/index.tsx`
- `3e78a275` 2024-05-16 "Update sdk. Add mobile payment for subscriptions"
- `9a7995d9` 2024-05-22 "Add mobile payments for capasitor. Add firebase notifications..." - `front/.env.example`, `front/.gitignore`, `front/resources/*`, `front/public/manifest.json`
