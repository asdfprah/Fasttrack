import type { QueryState } from './types.js'

export function createQueryState(): QueryState {
  return { wheres: {}, sorts: [], includes: [], fields: {}, extraParams: {} }
}

/**
 * Serializes a {@link QueryState} into a query string.
 *
 * @remarks
 * Verified against spatie/laravel-query-builder's own README rather than
 * guessed: `filter[field]=value`, `sort=field,-otherField`,
 * `include=relation,other.nested`, `fields[resourceType]=a,b`. `limit`/`offset`
 * are read directly by `Vifrost\Laravel\Pagination` on the Laravel side —
 * spatie's package doesn't own those, Vifrost does.
 */
export function buildQueryString(state: QueryState): string {
  const params = new URLSearchParams()

  for (const [field, value] of Object.entries(state.wheres)) {
    params.set(`filter[${field}]`, value)
  }
  if (state.sorts.length) {
    params.set('sort', state.sorts.join(','))
  }
  if (state.includes.length) {
    params.set('include', state.includes.join(','))
  }
  for (const [resourceType, fieldNames] of Object.entries(state.fields)) {
    params.set(`fields[${resourceType}]`, fieldNames.join(','))
  }
  if (state.limit !== undefined) {
    params.set('limit', String(state.limit))
  }
  if (state.offset !== undefined) {
    params.set('offset', String(state.offset))
  }
  for (const [key, value] of Object.entries(state.extraParams)) {
    params.set(key, value)
  }

  const serialized = params.toString()
  return serialized ? `?${serialized}` : ''
}
