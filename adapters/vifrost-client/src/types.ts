import type { Model } from './Model.js'
import type { QueryBuilder } from './QueryBuilder.js'
import type { Registry } from './Registry.js'

/**
 * Configuration accepted by {@link HttpClient}.
 */
export interface HttpClientOptions {
  baseUrl: string
  headers?: Record<string, string>
  fetch?: typeof fetch
}

/**
 * Configuration accepted by {@link configure}.
 *
 * @remarks
 * `registry` is optional and opt-in by design: a consumer who only wants the
 * plain query API (no identity map, no `save`/`delete` publishing) never
 * constructs a {@link Registry}, and none of that code path ever runs.
 */
export interface ConfigureOptions extends HttpClientOptions {
  registry?: Registry
}

/**
 * Mutable state accumulated by {@link QueryBuilder} before being serialized
 * into a query string.
 */
export interface QueryState {
  wheres: Record<string, string>
  sorts: string[]
  includes: string[]
  fields: Record<string, string[]>
  limit?: number
  offset?: number
  extraParams: Record<string, string>
}

/**
 * The static side of a {@link Model} subclass, as emitted by
 * `@vifrost/codegen`.
 */
export interface ModelConstructor<T extends Model = Model> {
  new (attributes?: Record<string, unknown>): T
  resource: string
  primaryKey: string
  /**
   * Max rows a collection query for this model may request — mirrors
   * `Vifrost\Laravel\Pagination::maxLimitFor()` on the Laravel side. `null`
   * means the model is exempt from pagination entirely. Codegen'd classes always
   * set this precisely; hand-written subclasses default to `null` (no enforcement)
   * via the base `Model` class unless they opt in.
   */
  maxLimit: number | null
  instantiate<R extends Model>(this: ModelConstructor<R>, data: Record<string, unknown>): R
  find<R extends Model>(this: ModelConstructor<R>, id: string | number): Promise<R>
  query<R extends Model>(this: ModelConstructor<R>): QueryBuilder<R>
}

/** Re-fetches the current value tracked by a {@link Registry} entry. */
export type ResyncFn<T> = () => Promise<T>

export interface RegistryUpdatedEvent<T> {
  type: 'updated'
  value: T
}

export interface RegistryDeletedEvent {
  type: 'deleted'
}

/** What a {@link Registry} subscriber receives — never a bare value. */
export type RegistryEvent<T> = RegistryUpdatedEvent<T> | RegistryDeletedEvent

export type RegistryListener<T> = (event: RegistryEvent<T>) => void

/** One {@link Registry} bookkeeping entry. */
export interface RegistryEntry<T> {
  value: T
  resync: ResyncFn<T>
  listeners: Set<RegistryListener<T>>
}
