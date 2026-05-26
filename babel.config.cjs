/**
 * Extract translatable strings from admin UI sources (TypeScript/React).
 * Same Sikshya-style merge as scripts/makepot.sh: fragment → wp i18n make-pot --merge.
 *
 * We scan client/src, not the Vite production bundle: minified output has no
 * extractable __() / _n() / _x() call sites for @wordpress/babel-plugin-makepot.
 */
module.exports = {
  presets: [
    ['@babel/preset-env', { targets: { node: 'current' } }],
    ['@babel/preset-typescript', { isTSX: true, allExtensions: true }],
    ['@babel/preset-react', { runtime: 'automatic' }],
  ],
  plugins: [
    [
      '@wordpress/babel-plugin-makepot',
      {
        output: 'languages/sikshya-js.pot',
        domain: 'sikshya',
        /**
         * Must list every function used for user-facing strings in client/src.
         * Only string literals at the call site are extracted (not variables).
         */
        functions: {
          __: ['msgid'],
          _n: ['msgid', 'msgid_plural'],
          _x: ['msgid', 'msgctxt'],
          _nx: ['msgid', 'msgid_plural', null, 'msgctxt'],
          /** client/src/lib/i18n.ts — same argument layout as __ */
          translate: ['msgid'],
          t: ['msgid'],
        },
      },
    ],
  ],
};
