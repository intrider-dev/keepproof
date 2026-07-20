import { spawnSync } from 'node:child_process'
import { cpSync, existsSync, mkdtempSync, readFileSync, readdirSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const scriptDirectory = dirname(fileURLToPath(import.meta.url))
const projectRoot = dirname(scriptDirectory)
const repositoryRoot = resolve(projectRoot, '../..')
const trackedOutput = join(projectRoot, 'src/api/generated')
const temporaryRoot = mkdtempSync(join(tmpdir(), 'keepproof-openapi-'))
const temporaryOutput = join(temporaryRoot, 'generated')
const checkOnly = process.argv.includes('--check')

function listFiles(root, directory = root) {
    if (!existsSync(directory)) {
        return []
    }

    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const entryPath = join(directory, entry.name)

        return entry.isDirectory() ? listFiles(root, entryPath) : [relative(root, entryPath)]
    })
}

function changedFiles(expected, actual) {
    const paths = new Set([...listFiles(expected), ...listFiles(actual)])

    return [...paths].sort().filter((path) => {
        const expectedPath = join(expected, path)
        const actualPath = join(actual, path)

        return (
            !existsSync(expectedPath) ||
            !existsSync(actualPath) ||
            !readFileSync(expectedPath).equals(readFileSync(actualPath))
        )
    })
}

let exitCode = 0

try {
    const executable = process.execPath
    const generator = join(projectRoot, 'node_modules/@hey-api/openapi-ts/bin/run.js')
    const generation = spawnSync(
        executable,
        [
            generator,
            '-i',
            join(repositoryRoot, 'apps/backend/openapi/openapi.yaml'),
            '-o',
            temporaryOutput,
        ],
        {
            cwd: projectRoot,
            stdio: 'inherit',
        },
    )

    if (generation.status !== 0) {
        exitCode = generation.status ?? 1
    } else if (checkOnly) {
        const differences = changedFiles(temporaryOutput, trackedOutput)

        if (differences.length > 0) {
            console.error('The generated OpenAPI client is stale:')
            differences.forEach((path) => console.error(`- ${path}`))
            console.error('Run `npm run generate:api` and commit the result.')
            exitCode = 1
        } else {
            console.log('The generated OpenAPI client is current.')
        }
    } else {
        // Generate outside the bind mount, then replace the output as one bounded step.
        // This avoids EINVAL failures seen when the generator rewrites existing files on Docker Desktop.
        rmSync(trackedOutput, { force: true, recursive: true })
        cpSync(temporaryOutput, trackedOutput, { recursive: true })
        console.log('Generated src/api/generated from the tracked OpenAPI contract.')
    }
} finally {
    rmSync(temporaryRoot, { force: true, recursive: true })
}

process.exitCode = exitCode
