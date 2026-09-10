// Scope drives which package's semantic-release run a commit counts toward
// (see the .releaserc.json files) — mandatory and closed to these four so a
// typo or a missing scope fails the commit instead of silently not
// triggering (or worse, triggering the wrong package's) a release.
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'scope-enum': [2, 'always', ['client', 'codegen', 'vue', 'laravel']],
    'scope-empty': [2, 'never'],
  },
  // semantic-release's own auto-generated release commits (see the
  // .releaserc.json `message` templates) embed the full changelog as the
  // commit body — long commit-link lines routinely blow past
  // body-max-line-length. These commits are machine-generated and already
  // well-formed by construction, so skip linting them entirely instead of
  // relaxing the rule for real human commits too.
  ignores: [(commit) => /^chore\((client|codegen|vue|laravel)\): release v/.test(commit)],
}
