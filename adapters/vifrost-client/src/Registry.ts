import type { RegistryEntry, RegistryEvent, RegistryListener, ResyncFn } from './types.js'

/**
 * Identity map keyed by `${resourceType}:${id}`, tracking every {@link Model}
 * instance a `find`/`all`/`query`/relation call has fetched.
 *
 * @remarks
 * Built so a future real-time transport (a WebSocket "this record changed"
 * event, an SSE stream, polling — anything) can drive
 * {@link Registry.resync} without this package needing to know about
 * Vue/React/Svelte reactivity at all. Turning a notified update into a
 * re-render is a thin per-framework binding layer built on top of this, not
 * this class's concern.
 */
export class Registry {
  private readonly entries = new Map<string, RegistryEntry<unknown>>()

  private keyFor(resourceType: string, id: string | number): string {
    return `${resourceType}:${id}`
  }

  private notify<T>(entry: RegistryEntry<T>, event: RegistryEvent<T>): void {
    for (const listener of entry.listeners) {
      listener(event)
    }
  }

  /**
   * Registers a fresh value under `${resourceType}:${id}`.
   *
   * @remarks
   * Notifies existing subscribers when it updates an entry that was already
   * tracked (a re-fetch, or `Model#save()` on a record obtained earlier)
   * but not on first registration — nothing could be subscribed to an id
   * that wasn't tracked yet. This is what makes `Model#save()` participate
   * in the same identity map as `find`/`all`/`query` without a separate
   * "publish" concept: any code path that hands the registry a fresh value
   * for a tracked record propagates it the same way.
   */
  track<T>(resourceType: string, id: string | number, value: T, resync: ResyncFn<T>): void {
    const key = this.keyFor(resourceType, id)
    const existingEntry = this.entries.get(key) as RegistryEntry<T> | undefined
    if (existingEntry) {
      existingEntry.value = value
      existingEntry.resync = resync
      this.notify(existingEntry, { type: 'updated', value })
      return
    }
    this.entries.set(key, { value, resync, listeners: new Set() } as RegistryEntry<unknown>)
  }

  /**
   * @returns an unsubscribe function
   * @throws if `resourceType`/`id` hasn't been {@link Registry.track}ed yet
   */
  subscribe<T>(resourceType: string, id: string | number, listener: RegistryListener<T>): () => void {
    const key = this.keyFor(resourceType, id)
    const entry = this.entries.get(key)
    if (!entry) {
      throw new Error(`Cannot subscribe to ${key}: it has not been tracked yet. Fetch it first.`)
    }
    const typedListener = listener as RegistryListener<unknown>
    entry.listeners.add(typedListener)
    return () => entry.listeners.delete(typedListener)
  }

  /**
   * Re-fetches a tracked record and publishes the fresh value to its
   * subscribers. A no-op if the record isn't currently tracked. This is what
   * a future WebSocket "record changed" handler calls.
   */
  async resync(resourceType: string, id: string | number): Promise<void> {
    const entry = this.entries.get(this.keyFor(resourceType, id))
    if (!entry) {
      return
    }
    const value = await entry.resync()
    entry.value = value
    this.notify(entry, { type: 'updated', value })
  }

  /**
   * Notifies subscribers the record was deleted (e.g. by `Model#delete()`),
   * then stops tracking it. A no-op if the record isn't currently tracked.
   */
  remove(resourceType: string, id: string | number): void {
    const key = this.keyFor(resourceType, id)
    const entry = this.entries.get(key)
    if (entry) {
      this.notify(entry, { type: 'deleted' })
    }
    this.entries.delete(key)
  }

  isTracked(resourceType: string, id: string | number): boolean {
    return this.entries.has(this.keyFor(resourceType, id))
  }
}
