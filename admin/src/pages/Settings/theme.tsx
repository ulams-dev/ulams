import { Alert, Button, ConfigProvider, Input, Radio, Space, message } from 'antd';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useIntl } from 'umi';

import {
  createSettings,
  deleteSettings,
  publicSettings,
  updateSettings,
} from '@/services/ulams/settings';
import {
  logoProblem,
  refusal,
  removeLogo,
  themeSettings,
  themeValues,
  uploadLogo,
  type ThemeValues,
} from '@/services/ulams/theme';
import {
  proposeBrandTheme,
  themeDiff,
  type BrandProposal,
} from '../../../../front/ui/src/theme/brand-import';
import { adjustAccent } from '../../../../front/ui/src/theme/contrast';
import {
  TENANT_THEMES,
  THEME_PRESETS,
  type TenantThemeName,
} from '../../../../front/ui/src/theme/presets';
import { contrastReport, normaliseAccent } from '../../../../front/ui/src/theme/report';

/**
 * Settings → Theme: the preset, the accent colour with its WCAG contrast report, the logo and the import
 * of brand colours as a diff to approve. Saving writes `theme.theme`, `theme.accent`, `theme.logo` and
 * `theme.logoAlt` (the settings service and the logo endpoint); an accent that fails AA on the chosen preset
 * is not saved, and the report says which check fails and by how much.
 */

const CSS = `
.theme-settings section{margin-bottom:24px}
.theme-presets{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;width:100%}
.theme-preset{display:flex;align-items:flex-start;margin:0;padding:8px;border:1px solid var(--ant-color-border,#d9d9d9);border-radius:8px}
.theme-card{display:flex;flex-direction:column;gap:4px;padding:10px;border:1px solid;border-radius:6px;min-width:160px}
.theme-card__panel{display:flex;gap:8px;align-items:center;padding:8px;border:1px solid;border-radius:4px;font-weight:600}
.theme-card__button{padding:2px 10px;border-radius:4px}
.theme-card__name{font-weight:600}
.theme-card__mood{font-size:12px}
.theme-hint{color:var(--ant-color-text-secondary,#595959);margin:4px 0 8px}
.theme-fail{font-weight:600;color:var(--ant-color-error-text,#a8071a)}
.theme-report{border-collapse:collapse;width:100%;max-width:960px}
.theme-report th,.theme-report td{border-bottom:1px solid var(--ant-color-border-secondary,#e0e0e0);padding:6px 8px;text-align:left}
.theme-report tr[data-pass="false"] td:last-child{font-weight:600;color:var(--ant-color-error-text,#a8071a)}
.theme-chip{display:inline-flex;width:20px;height:20px;border:1px solid #767676;border-radius:4px;margin-right:8px;vertical-align:middle;align-items:center;justify-content:center}
.theme-chip span{display:block;width:10px;height:10px}
.theme-logo{min-height:48px;margin-bottom:8px}
.theme-logo__img{max-height:56px;max-width:240px}
.theme-diff{margin-top:12px;padding:12px;border:1px solid var(--ant-color-border,#d9d9d9);border-radius:6px;max-width:720px}
.theme-diff table{border-collapse:collapse;width:100%}
.theme-diff th,.theme-diff td{padding:4px 8px;text-align:left;border-bottom:1px solid var(--ant-color-border-secondary,#e0e0e0)}
.theme-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
`;

type Draft = { theme: TenantThemeName; accent: string; alt: string };

const isPreset = (value: unknown): value is TenantThemeName =>
  typeof value === 'string' && (TENANT_THEMES as readonly string[]).includes(value);

function PresetCard({ name, accent }: { name: TenantThemeName; accent: string | null }) {
  const p = THEME_PRESETS[name];
  const fill = accent ? adjustAccent(accent, p.bg, 5)?.value ?? p.primary : p.primary;
  return (
    <span className="theme-card" style={{ background: p.bg, color: p.text, borderColor: p.border }}>
      <span
        className="theme-card__panel"
        style={{ background: p.card, borderColor: p.border }}
        aria-hidden="true"
      >
        <span style={{ color: p.text }}>Aa</span>
        <span style={{ color: p.muted }}>Aa</span>
        <span className="theme-card__button" style={{ background: fill, color: p.onPrimary }}>
          Aa
        </span>
      </span>
      <span className="theme-card__name">{p.label}</span>
      <span className="theme-card__mood" style={{ color: p.muted }}>
        {p.mood}
      </span>
    </span>
  );
}

export default function ThemeSettings() {
  const intl = useIntl();
  const t = (id: string, values?: Record<string, string | number>) =>
    intl.formatMessage({ id: `theme.${id}` }, values);

  const [loaded, setLoaded] = useState(false);
  const [stored, setStored] = useState<ThemeValues>({});
  const [company, setCompany] = useState('');
  const [draft, setDraft] = useState<Draft>({ theme: 'coffee', accent: '', alt: '' });
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [dropLogo, setDropLogo] = useState(false);
  const [logoError, setLogoError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [blocked, setBlocked] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);
  const [brandText, setBrandText] = useState('');
  const [proposal, setProposal] = useState<BrandProposal | null | undefined>(undefined);
  const fileInput = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    const [rows, pub] = await Promise.all([themeSettings(), publicSettings().catch(() => null)]);
    const values = themeValues(rows.success ? rows.data : []);
    setStored(values);
    setCompany(String((pub?.success && pub.data?.global?.companyName) || ''));
    setDraft({
      theme: isPreset(values.theme?.value) ? values.theme.value : 'coffee',
      accent: values.accent?.value ?? '',
      alt: values.logoAlt?.value ?? '',
    });
    setFile(null);
    setDropLogo(false);
    setLoaded(true);
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (!file) return setPreview(null);
    const url = URL.createObjectURL(file);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);

  const accent = normaliseAccent(draft.accent);
  const accentInvalid = draft.accent.trim() !== '' && accent === null;
  const report = useMemo(() => contrastReport(draft.theme, accent ?? ''), [draft.theme, accent]);
  const preset = THEME_PRESETS[draft.theme];
  const currentLogo = dropLogo ? null : preview ?? stored.logo?.data ?? null;

  const pickFile = (candidate: File | null) => {
    setLogoError(null);
    if (!candidate) return setFile(null);
    const problem = logoProblem(candidate);
    if (problem) {
      setFile(null);
      if (fileInput.current) fileInput.current.value = '';
      return setLogoError(t(problem === 'type' ? 'logo_type' : 'logo_size'));
    }
    setDropLogo(false);
    setFile(candidate);
  };

  const dirty =
    draft.theme !== (stored.theme?.value ?? 'coffee') ||
    (accent ?? '') !== (normaliseAccent(stored.accent?.value) ?? '') ||
    draft.alt !== (stored.logoAlt?.value ?? '') ||
    file !== null ||
    dropLogo;

  const put = async (key: 'theme' | 'accent' | 'logoAlt', value: string) => {
    const row = stored[key];
    if (row) return updateSettings(row.id, { value });
    return createSettings({
      group: 'theme',
      key,
      type: 'text',
      value,
      public: true,
      enumerable: true,
    });
  };

  const save = async (next: Draft = draft) => {
    const nextAccent = normaliseAccent(next.accent);
    const failures = contrastReport(next.theme, nextAccent ?? '').failures;
    if (next.accent.trim() !== '' && (nextAccent === null || failures.length > 0)) {
      setBlocked(true);
      return false;
    }
    setBlocked(false);
    setFailure(null);
    setSaving(true);
    try {
      if (file) {
        await uploadLogo(file, next.alt);
      } else if (dropLogo && stored.logo) {
        await removeLogo();
      } else if (stored.logo && next.alt !== (stored.logoAlt?.value ?? '')) {
        if (next.alt.trim() === '' && stored.logoAlt) await deleteSettings(stored.logoAlt.id);
        else if (next.alt.trim() !== '') await put('logoAlt', next.alt.trim());
      }
      if (next.theme !== (stored.theme?.value ?? '')) await put('theme', next.theme);
      if (nextAccent && nextAccent !== stored.accent?.value) await put('accent', nextAccent);
      if (!nextAccent && stored.accent) await deleteSettings(stored.accent.id);
      message.success(t('saved'));
      await load();
      return true;
    } catch (error) {
      setFailure(refusal(error) ?? t('save_failed'));
      return false;
    } finally {
      setSaving(false);
    }
  };

  const propose = () => setProposal(proposeBrandTheme(brandText));

  /** The accent the proposal would save: the brand colour, or its readable shade when that fails AA on the proposed preset. */
  const proposedAccent = useMemo(() => {
    if (!proposal) return null;
    const own = contrastReport(proposal.theme, proposal.accent);
    return own.failures.length === 0
      ? proposal.accent.toLowerCase()
      : own.suggestion ?? proposal.accent.toLowerCase();
  }, [proposal]);

  const diff = useMemo(
    () =>
      proposal && proposedAccent
        ? themeDiff(
            { ...proposal, accent: proposedAccent },
            { theme: stored.theme?.value, accent: stored.accent?.value },
          )
        : [],
    [proposal, proposedAccent, stored],
  );

  const approve = async () => {
    if (!proposal || !proposedAccent) return;
    const next = { ...draft, theme: proposal.theme, accent: proposedAccent };
    setDraft(next);
    if (await save(next)) {
      setProposal(undefined);
      setBrandText('');
    }
  };

  if (!loaded) return <p role="status">{t('loading')}</p>;

  const failureList = report.failures.map((c) => c.message);

  return (
    // the admin's default blue (#1890ff) is 3.4:1 with white text; this screen is held to WCAG 2.2 AA
    <ConfigProvider theme={{ token: { colorPrimary: '#0958d9' } }}>
      <div className="theme-settings">
        <style>{CSS}</style>
        <p>{t('intro')}</p>

        <section aria-labelledby="theme-preset">
          <h3 id="theme-preset">{t('section_preset')}</h3>
          <Radio.Group
            className="theme-presets"
            value={draft.theme}
            onChange={(e) => setDraft({ ...draft, theme: e.target.value })}
            aria-labelledby="theme-preset"
          >
            {TENANT_THEMES.map((name) => (
              <Radio key={name} value={name} className="theme-preset">
                <PresetCard name={name} accent={name === draft.theme ? accent : null} />
              </Radio>
            ))}
          </Radio.Group>
        </section>

        <section aria-labelledby="theme-accent">
          <h3 id="theme-accent">{t('section_accent')}</h3>
          <Space align="end" wrap>
            <div>
              <label htmlFor="theme-accent-input">{t('accent_label')}</label>
              <br />
              <Input
                id="theme-accent-input"
                value={draft.accent}
                placeholder="#c8102e"
                maxLength={7}
                style={{ width: 160 }}
                status={accentInvalid ? 'error' : undefined}
                aria-invalid={accentInvalid}
                aria-describedby="theme-accent-hint"
                onChange={(e) => setDraft({ ...draft, accent: e.target.value })}
              />
            </div>
            <div>
              <label htmlFor="theme-accent-picker">{t('accent_picker')}</label>
              <br />
              <input
                id="theme-accent-picker"
                type="color"
                value={accent ?? preset.primary}
                onChange={(e) => setDraft({ ...draft, accent: e.target.value })}
              />
            </div>
            {draft.accent !== '' && (
              <Button onClick={() => setDraft({ ...draft, accent: '' })}>
                {t('accent_clear')}
              </Button>
            )}
          </Space>
          <p id="theme-accent-hint" className="theme-hint">
            {accentInvalid ? t('accent_invalid') : t('accent_hint', { preset: preset.label })}
          </p>
        </section>

        <section aria-labelledby="theme-report">
          <h3 id="theme-report">{t('section_report')}</h3>
          <p aria-live="polite" className={report.failures.length ? 'theme-fail' : undefined}>
            {!report.valid
              ? t('report_none')
              : report.failures.length === 0
              ? t('report_pass')
              : t('report_fail', { count: report.failures.length })}
          </p>
          <table className="theme-report">
            <caption className="theme-sr">{t('report_caption', { preset: preset.label })}</caption>
            <thead>
              <tr>
                <th scope="col">{t('col_check')}</th>
                <th scope="col">{t('col_colours')}</th>
                <th scope="col">{t('col_ratio')}</th>
                <th scope="col">{t('col_needed')}</th>
                <th scope="col">{t('col_result')}</th>
              </tr>
            </thead>
            <tbody>
              {report.checks.map((c) => (
                <tr key={c.id} data-check={c.id} data-pass={c.pass}>
                  <th scope="row">{c.label}</th>
                  <td>
                    <span
                      className="theme-chip"
                      style={{ background: c.background }}
                      aria-hidden="true"
                    >
                      <span style={{ background: c.foreground }} />
                    </span>
                    {c.foreground} / {c.background}
                  </td>
                  <td>{c.ratio}:1</td>
                  <td>
                    {c.required}:1 ({c.criterion})
                  </td>
                  <td>{c.pass ? t('result_pass') : t('result_fail')}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {report.suggestion && (
            <p>
              <Button onClick={() => setDraft({ ...draft, accent: report.suggestion! })}>
                {t('use_suggestion', { colour: report.suggestion })}
              </Button>
            </p>
          )}
        </section>

        <section aria-labelledby="theme-logo">
          <h3 id="theme-logo">{t('section_logo')}</h3>
          <div className="theme-logo">
            {currentLogo ? (
              <img
                src={currentLogo}
                alt={draft.alt || company || t('logo_preview_alt')}
                className="theme-logo__img"
                data-testid="logo-preview"
              />
            ) : (
              <p className="theme-hint">{t('logo_none')}</p>
            )}
          </div>
          <Space align="end" wrap>
            <div>
              <label htmlFor="theme-logo-file">{t('logo_file')}</label>
              <br />
              <input
                id="theme-logo-file"
                ref={fileInput}
                type="file"
                accept=".png,.svg,image/png,image/svg+xml"
                aria-describedby="theme-logo-hint"
                onChange={(e) => pickFile(e.target.files?.[0] ?? null)}
              />
            </div>
            {(stored.logo || file) && !dropLogo && (
              <Button
                onClick={() => {
                  setFile(null);
                  if (fileInput.current) fileInput.current.value = '';
                  if (stored.logo) setDropLogo(true);
                }}
              >
                {t('logo_remove')}
              </Button>
            )}
            {dropLogo && <Button onClick={() => setDropLogo(false)}>{t('logo_keep')}</Button>}
          </Space>
          <p id="theme-logo-hint" className="theme-hint">
            {t('logo_hint')}
          </p>
          <div>
            <label htmlFor="theme-logo-alt">{t('logo_alt')}</label>
            <br />
            <Input
              id="theme-logo-alt"
              value={draft.alt}
              maxLength={120}
              style={{ maxWidth: 420 }}
              aria-describedby="theme-logo-alt-hint"
              onChange={(e) => setDraft({ ...draft, alt: e.target.value })}
            />
            <p id="theme-logo-alt-hint" className="theme-hint">
              {t('logo_alt_hint', { name: company || t('company_fallback') })}
            </p>
          </div>
          {logoError && <Alert type="error" showIcon message={logoError} role="alert" />}
          {dropLogo && <p className="theme-hint">{t('logo_removed_pending')}</p>}
        </section>

        <section aria-labelledby="theme-import">
          <h3 id="theme-import">{t('section_import')}</h3>
          <label htmlFor="theme-import-text">{t('import_label')}</label>
          <Input.TextArea
            id="theme-import-text"
            rows={4}
            value={brandText}
            placeholder={'{ "primary": "#c8102e", "background": "#ffffff" }'}
            aria-describedby="theme-import-hint"
            onChange={(e) => {
              setBrandText(e.target.value);
              setProposal(undefined);
            }}
          />
          <p id="theme-import-hint" className="theme-hint">
            {t('import_hint')}
          </p>
          <Button onClick={propose} disabled={brandText.trim() === ''}>
            {t('import_propose')}
          </Button>
          <div aria-live="polite">
            {proposal === null && <p>{t('import_none')}</p>}
            {proposal && proposedAccent && (
              <div className="theme-diff" data-testid="brand-diff">
                <h4>{t('diff_title')}</h4>
                <table>
                  <caption className="theme-sr">{t('diff_title')}</caption>
                  <thead>
                    <tr>
                      <th scope="col">{t('diff_field')}</th>
                      <th scope="col">{t('diff_before')}</th>
                      <th scope="col">{t('diff_after')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <th scope="row">{t('diff_theme')}</th>
                      <td>
                        {THEME_PRESETS[stored.theme?.value as TenantThemeName]?.label ??
                          t('diff_none')}
                      </td>
                      <td>{THEME_PRESETS[proposal.theme].label}</td>
                    </tr>
                    <tr>
                      <th scope="row">{t('diff_accent')}</th>
                      <td>{stored.accent?.value ?? t('diff_none')}</td>
                      <td>{proposedAccent}</td>
                    </tr>
                  </tbody>
                </table>
                {diff.length === 0 && <p>{t('diff_unchanged')}</p>}
                {proposedAccent !== proposal.accent.toLowerCase() && (
                  <p>{t('diff_adjusted', { brand: proposal.accent, shade: proposedAccent })}</p>
                )}
                <ul>
                  {proposal.notes.map((n) => (
                    <li key={n}>{n}</li>
                  ))}
                </ul>
                <Space>
                  <Button
                    type="primary"
                    onClick={approve}
                    loading={saving}
                    disabled={diff.length === 0}
                  >
                    {t('import_approve')}
                  </Button>
                  <Button onClick={() => setProposal(undefined)}>{t('import_discard')}</Button>
                </Space>
              </div>
            )}
          </div>
        </section>

        <div aria-live="assertive">
          {blocked && (
            <Alert
              type="error"
              showIcon
              role="alert"
              message={t('save_blocked')}
              description={
                <ul>
                  {(accentInvalid ? [t('accent_invalid')] : failureList).map((m) => (
                    <li key={m}>{m}</li>
                  ))}
                </ul>
              }
            />
          )}
          {failure && <Alert type="error" showIcon role="alert" message={failure} />}
        </div>

        <Space style={{ marginTop: 16 }}>
          <Button type="primary" onClick={() => save()} loading={saving} disabled={!dirty}>
            {t('save')}
          </Button>
          <Button onClick={() => load()} disabled={!dirty || saving}>
            {t('reset')}
          </Button>
        </Space>
      </div>
    </ConfigProvider>
  );
}
