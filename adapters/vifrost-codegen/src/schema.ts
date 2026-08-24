import { readFile } from 'node:fs/promises'
import type { VifrostSchema } from './types.js'

/** Bump alongside `Vifrost\Laravel\SchemaExporter::SCHEMA_VERSION` on the PHP side. */
export const SUPPORTED_SCHEMA_VERSION = 1

async function readSchemaSource(source: string): Promise<string> {
  const isHttpUrl = /^https?:\/\//.test(source)
  if (!isHttpUrl) {
    return readFile(source, 'utf-8')
  }

  const response = await fetch(source)
  if (!response.ok) {
    throw new Error(`Failed to fetch schema from ${source}: HTTP ${response.status}`)
  }
  return response.text()
}

/**
 * Loads a Vifrost schema export from a local file path or an http(s) URL
 * (the `vifrost.expose_schema_route` endpoint).
 *
 * @throws if the JSON is malformed, or its `schemaVersion` isn't one this
 * generator understands
 */
export async function loadSchema(source: string): Promise<VifrostSchema> {
  const rawSchema = await readSchemaSource(source)

  let schema: VifrostSchema
  try {
    schema = JSON.parse(rawSchema) as VifrostSchema
  } catch (cause) {
    throw new Error(`Failed to parse schema JSON from ${source}: ${(cause as Error).message}`)
  }

  if (schema.schemaVersion !== SUPPORTED_SCHEMA_VERSION) {
    throw new Error(
      `Unsupported Vifrost schema version ${schema.schemaVersion} (this generator supports version ${SUPPORTED_SCHEMA_VERSION}). ` +
        'Check for a newer @vifrost/codegen release.'
    )
  }

  return schema
}
