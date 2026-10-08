# Ulams LMS

[![End-to-end Tests](https://github.com/EscolaLMS/Admin/actions/workflows/e2e-playwright.js.yml/badge.svg)](https://github.com/EscolaLMS/Admin/actions/workflows/e2e-playwright.js.yml)

This project is initialized with [Ant.design PRO](https://pro.ant.design). Follow is the quick guide for how to use.

Documentation on how to use admin panel are available at [docs.ulams.app](https://docs.ulams.app/)

## Environmental Variables

You need to provide URL to API for Admin to work

example

```
REACT_APP_API_URL='https://api-stage.ulams.app'
```

those can be in `.env` file or in Environmental Variables

## Environment Prepare

Install `node_modules`:

```bash
yarn
```

## Provided Scripts

Ulams LMS provides some useful script to help you quick start and build with web project, code style check and test.

Scripts provided in `package.json`. It's safe to modify or add additional script:

### Start project

```bash
yarn start
```

### Build project

```bash
yarn build
```

#### e2e tests

Locally

```bash
yarn playwright:headed
```

CI

```bash
yarn e2e:test
```

## More
