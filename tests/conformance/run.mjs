// Runs the Panneau protocol's conformance kit against the app served by `vendor/bin/testbench serve`
// (testbench.yaml): the articles resource, whose repository is a Folklore repository.
//
//   node tests/conformance/run.mjs <bundle of @panneau/protocol/conformance> [baseUrl]
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';

const [bundle, baseUrl = 'http://127.0.0.1:8000/panneau/api'] = process.argv.slice(2);
if (bundle === undefined) {
    console.error('Usage: node tests/conformance/run.mjs <conformance bundle> [baseUrl]');
    process.exit(2);
}

// Checks of what the fixture app doesn't have, since they don't involve the repository
const NOT_APPLICABLE = new Set(['action.dry-run', 'uploads.sign']);

const { runConformance, formatReport } = await import(pathToFileURL(resolve(bundle)).href);

const report = await runConformance({
    baseUrl,
    init: { headers: { Authorization: 'Bearer conformance' } },
    resource: 'articles',
    writes: {
        create: { title: 'Conformance', slug: { fr: 'conformite', en: 'conformance' }, status: 'draft' },
        update: { title: 'Conformance 2' },
    },
});

console.log(formatReport(report));

// Any other check applies to the fixture app: a skipped check means something is missing.
const skipped = report.checks.filter((check) => check.status === 'skipped' && !NOT_APPLICABLE.has(check.id));
if (skipped.length > 0) {
    console.error(`\n${skipped.length} check(s) were skipped: ${skipped.map((check) => check.id).join(', ')}.`);
}
process.exit(report.passed && skipped.length === 0 ? 0 : 1);
