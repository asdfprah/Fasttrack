import type { UnloadedRelationAccess } from './types.js'

/**
 * Property names JS/frameworks probe on arbitrary values without the caller
 * "meaning" to read relation data — letting these through silently avoids
 * false-positive warnings on internal coercion/thenable checks.
 */
const SAFE_PROBE_PROPERTIES: ReadonlySet<PropertyKey> = new Set(['then', 'constructor', Symbol.toPrimitive])

/**
 * Wraps a relation-loader method (e.g. `product.category`, still resolving to
 * the raw prototype method because the row wasn't fetched with
 * `.with('category')`) so that *calling* it — the correct, intended use —
 * behaves exactly as before, while reading any other property off it first
 * (`.name`, `?.something`, treating it as data) triggers the configured
 * {@link UnloadedRelationAccess}.
 *
 * @remarks
 * This solely reason of this is to prevent errors by making developers see
 * the usage of relations which hasnt been loaded yet
 */
function wrapRelationLoader(
  loaderFn: (...args: unknown[]) => unknown,
  relationName: string,
  mode: UnloadedRelationAccess
): unknown {
  return new Proxy(loaderFn, {
    apply(target, thisArg, args) {
      return Reflect.apply(target, thisArg, args)
    },
    get(target, prop, receiver) {
      if (SAFE_PROBE_PROPERTIES.has(prop)) {
        return Reflect.get(target, prop, receiver)
      }

      const message =
        `[vifrost] "${relationName}" hasn't been loaded — fetch it with .with("${relationName}") ` +
        `or call .${relationName}() first. Tried to read "${String(prop)}" directly on the relation loader.`

      if (mode === 'error') {
        throw new Error(message)
      }

      console.warn(message)
      return undefined
    },
  })
}

/**
 * Wraps a freshly-instantiated model in a `Proxy` that applies
 * {@link wrapRelationLoader} to whichever declared relations weren't loaded
 * on this particular row — or returns the instance untouched if every
 * declared relation is either loaded or there are none, so the common case
 * pays zero overhead.
 *
 * @param instance the model instance to (maybe) wrap
 * @param unloadedRelationNames relation names present in `ModelConstructor.relations`
 *   but absent from this row's raw attributes
 * @param mode see {@link UnloadedRelationAccess}; `'off'` is never passed
 *   in — the caller skips this function entirely in that case
 */
export function guardUnloadedRelations<T extends object>(
  instance: T,
  unloadedRelationNames: string[],
  mode: Exclude<UnloadedRelationAccess, 'off'>
): T {
  if (unloadedRelationNames.length === 0) {
    return instance
  }

  const unloaded = new Set(unloadedRelationNames)

  return new Proxy(instance, {
    get(target, prop, receiver) {
      const value = Reflect.get(target, prop, receiver)
      if (typeof prop === 'string' && unloaded.has(prop) && typeof value === 'function') {
        return wrapRelationLoader(value as (...args: unknown[]) => unknown, prop, mode)
      }
      return value
    },
  })
}
