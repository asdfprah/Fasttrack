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
}
