const { BUDGET_BYTES, checkBudget } = require('./editor-budget.cjs');

const { randomBytes } = require('node:crypto');

// Random bytes do not compress, so the gzip size is the raw size.
const noise = (bytes) => randomBytes(bytes).toString('latin1');
const file = (name, text) => ({ name, content: Buffer.from(text, 'latin1') });

describe('editor size budget', () => {
  it('passes when the editor chunk is lazy and small', () => {
    const result = checkBudget(
      [file('umi.js', 'app code'), file('async/editor.js', `/*! grapesjs-mjml */${noise(10_000)}`)],
      '<script src="/umi.js"></script>',
    );
    expect(result.problems).toEqual([]);
    expect(result.files).toEqual(['async/editor.js']);
  });

  it('fails when the chunk grows past the budget', () => {
    const result = checkBudget(
      [file('async/editor.js', `/*! grapesjs-mjml */${noise(BUDGET_BYTES + 50_000)}`)],
      '',
    );
    expect(result.problems.join(' ')).toMatch(/budget is 700 KB/);
  });

  it('fails when the editor is in a file loaded by index.html', () => {
    const result = checkBudget(
      [file('umi.js', `/*! grapesjs-mjml */${noise(1000)}`)],
      '<script src="/umi.js"></script>',
    );
    expect(result.problems.join(' ')).toMatch(/entry bundle/);
  });

  it('fails when no file carries the editor', () => {
    expect(checkBudget([file('umi.js', 'x')], '').problems.join(' ')).toMatch(/no built file/);
  });
});
