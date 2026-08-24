import { mkdtemp, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { loadSchema, SUPPORTED_SCHEMA_VERSION } from '../src/schema.js'

const FIXTURE_PATH = new URL('./fixtures/schema.json', import.meta.url).pathname

describe('loadSchema', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('loads and parses a schema from a local file path', async () => {
    const schema = await loadSchema(FIXTURE_PATH)

    expect(schema.schemaVersion).toBe(SUPPORTED_SCHEMA_VERSION)
    expect(schema.models['App\\Models\\Product'].resource).toBe('product')
  })

  it('loads and parses a schema from an http(s) URL', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ schemaVersion: 1, models: {} }), { status: 200 })
    )
    vi.stubGlobal('fetch', fetchMock)

    const schema = await loadSchema('https://example.test/api/_schema')

    expect(fetchMock).toHaveBeenCalledWith('https://example.test/api/_schema')
    expect(schema.models).toEqual({})
  })

  it('throws a clear error when the HTTP fetch fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(null, { status: 500 })))

    await expect(loadSchema('https://example.test/api/_schema')).rejects.toThrow(/HTTP 500/)
  })

  it('rejects a schemaVersion this generator does not understand', async () => {
    const dir = await mkdtemp(path.join(tmpdir(), 'vifrost-codegen-'))
    const file = path.join(dir, 'schema.json')
    await writeFile(file, JSON.stringify({ schemaVersion: 999, models: {} }))

    await expect(loadSchema(file)).rejects.toThrow(/Unsupported Vifrost schema version 999/)
  })

  it('raises a clear error on malformed JSON instead of a cryptic parser exception', async () => {
    const dir = await mkdtemp(path.join(tmpdir(), 'vifrost-codegen-'))
    const file = path.join(dir, 'schema.json')
    await writeFile(file, '{ not valid json')

    await expect(loadSchema(file)).rejects.toThrow(/Failed to parse schema JSON/)
  })
})
