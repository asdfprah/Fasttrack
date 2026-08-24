import { HttpClient } from './HttpClient.js'
import type { ConfigureOptions } from './types.js'
import type { Registry } from './Registry.js'

let sharedHttpClient: HttpClient | null = null
let sharedRegistry: Registry | null = null

/** Call once, before any {@link Model} method is used. */
export function configure(options: ConfigureOptions): void {
  sharedHttpClient = new HttpClient(options)
  sharedRegistry = options.registry ?? null
}

/** @throws if {@link configure} hasn't been called yet */
export function client(): HttpClient {
  if (!sharedHttpClient) {
    throw new Error('Vifrost client not configured. Call configure({ baseUrl }) once before using any Model.')
  }
  return sharedHttpClient
}

/**
 * @returns the {@link Registry} passed to {@link configure}, or `null` if
 * none was given — the reactive identity map is opt-in, so `Model` skips it
 * entirely for a consumer who only wants the plain query API.
 */
export function getRegistry(): Registry | null {
  return sharedRegistry
}

/** Test-only: clears the configured client/registry so each test starts clean. */
export function resetClient(): void {
  sharedHttpClient = null
  sharedRegistry = null
}
