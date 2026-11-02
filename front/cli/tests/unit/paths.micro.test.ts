import { describe, it, expect } from "vitest";
import { configDir, configFile, credentialsFile } from '../../src/config/paths';

describe('config/paths', () => {
  it('returns ULAMS_CONFIG_DIR if set', () => {
    process.env.ULAMS_CONFIG_DIR = '/custom/config/dir';
    expect(configDir()).toBe('/custom/config/dir');
    delete process.env.ULAMS_CONFIG_DIR;
  });



  it('returns the correct path for non-Windows if XDG_CONFIG_HOME is set', () => {
    process.env.XDG_CONFIG_HOME = '/home/test/.config';
    expect(configDir()).toBe('/home/test/.config/ulams');
    delete process.env.XDG_CONFIG_HOME;
  });


  it('returns the correct config file path', () => {
    const dir = '/test/config/dir';
    expect(configFile(dir)).toBe('/test/config/dir/config.json');
  });

  it('returns the correct credentials file path', () => {
    const dir = '/test/config/dir';
    expect(credentialsFile(dir)).toBe('/test/config/dir/credentials.json');
  });
});
