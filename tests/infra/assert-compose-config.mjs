import assert from "node:assert/strict";

const input = await new Promise((resolve, reject) => {
    let value = "";
    process.stdin.setEncoding("utf8");
    process.stdin.on("data", (chunk) => {
        value += chunk;
    });
    process.stdin.on("end", () => resolve(value));
    process.stdin.on("error", reject);
});

const mode = process.argv[2];
const expected = process.argv[3];
const config = JSON.parse(input);
const backend = config.services?.backend?.environment;
const objectStorage = config.services?.["object-storage"];

assert.ok(
    backend,
    "The rendered Compose configuration must contain backend environment values.",
);
assert.equal(backend.AWS_ENDPOINT, "http://object-storage:8333");
assert.equal(backend.AWS_URL, undefined);
assert.ok(
    objectStorage,
    "The rendered Compose configuration must contain object storage.",
);
assert.equal(
    objectStorage.ports?.length ?? 0,
    0,
    "Private object storage must not publish a host port.",
);

if (mode === "development") {
    const origin = `http://localhost:${expected}`;
    assert.equal(backend.APP_PORT, expected);
    assert.equal(backend.APP_URL, origin);
    assert.equal(backend.FRONTEND_URL, origin);
    assert.ok(
        backend.SANCTUM_STATEFUL_DOMAINS.split(",").includes(
            `localhost:${expected}`,
        ),
    );
    assert.ok(
        backend.SANCTUM_STATEFUL_DOMAINS.split(",").includes(
            `127.0.0.1:${expected}`,
        ),
    );
    assert.ok(backend.CORS_ALLOWED_ORIGINS.split(",").includes(origin));
    assert.ok(
        backend.CORS_ALLOWED_ORIGINS.split(",").includes(
            `http://127.0.0.1:${expected}`,
        ),
    );
    assert.equal(config.services?.nginx?.ports?.[0]?.published, expected);
    assert.equal(
        config.services?.e2e?.environment?.PLAYWRIGHT_PUBLIC_ORIGIN,
        origin,
    );
} else if (mode === "production") {
    assert.equal(backend.APP_URL, expected);
    assert.equal(backend.FRONTEND_URL, expected);
    assert.equal(backend.SANCTUM_STATEFUL_DOMAINS, new URL(expected).host);
    assert.equal(backend.CORS_ALLOWED_ORIGINS, expected);
    assert.equal(backend.APP_ENV, "production");
    assert.equal(backend.APP_DEBUG, "false");
} else {
    throw new Error(`Unknown Compose assertion mode: ${String(mode)}`);
}

process.stdout.write(`Compose ${mode} origin contract passed.\n`);
