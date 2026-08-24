import { describe, expect, it, vi } from 'vitest'
import { Registry } from '../src/Registry.js'

describe('Registry', () => {
  it('is not tracked until track() is called', () => {
    const registry = new Registry()
    expect(registry.isTracked('product', 1)).toBe(false)
  })

  it('tracks a value keyed by resourceType + id', () => {
    const registry = new Registry()
    registry.track('product', 1, { id: 1, name: 'Gadget' }, async () => ({ id: 1, name: 'Gadget' }))

    expect(registry.isTracked('product', 1)).toBe(true)
    expect(registry.isTracked('product', 2)).toBe(false)
    expect(registry.isTracked('category', 1)).toBe(false)
  })

  it('throws when subscribing to something that was never tracked', () => {
    const registry = new Registry()
    expect(() => registry.subscribe('product', 1, () => {})).toThrow(/has not been tracked/)
  })

  it('resync() re-fetches and notifies every subscriber with an "updated" event', async () => {
    const registry = new Registry()
    let version = 1
    registry.track('product', 1, { id: 1, name: 'v1' }, async () => ({ id: 1, name: `v${++version}` }))

    const listenerA = vi.fn()
    const listenerB = vi.fn()
    registry.subscribe('product', 1, listenerA)
    registry.subscribe('product', 1, listenerB)

    await registry.resync('product', 1)

    expect(listenerA).toHaveBeenCalledWith({ type: 'updated', value: { id: 1, name: 'v2' } })
    expect(listenerB).toHaveBeenCalledWith({ type: 'updated', value: { id: 1, name: 'v2' } })
  })

  it('resync() on an untracked record is a no-op, not an error', async () => {
    const registry = new Registry()
    await expect(registry.resync('product', 999)).resolves.toBeUndefined()
  })

  it('track() notifies subscribers when it updates an already-tracked entry (e.g. Model#save())', () => {
    const registry = new Registry()
    const resync = vi.fn(async () => ({ id: 1, name: 'never called' }))
    registry.track('product', 1, { id: 1, name: 'v1' }, resync)

    const listener = vi.fn()
    registry.subscribe('product', 1, listener)

    registry.track('product', 1, { id: 1, name: 'v2 from save()' }, resync)

    expect(listener).toHaveBeenCalledWith({ type: 'updated', value: { id: 1, name: 'v2 from save()' } })
    expect(resync).not.toHaveBeenCalled()
  })

  it('track() does not (and cannot) notify anyone on first registration — nothing could be subscribed yet', () => {
    const registry = new Registry()
    expect(() => registry.track('product', 1, { id: 1 }, async () => ({ id: 1 }))).not.toThrow()
  })

  it('unsubscribe stops further notifications', async () => {
    const registry = new Registry()
    registry.track('product', 1, { id: 1 }, async () => ({ id: 1 }))

    const listener = vi.fn()
    const unsubscribe = registry.subscribe('product', 1, listener)
    unsubscribe()

    await registry.resync('product', 1)

    expect(listener).not.toHaveBeenCalled()
  })

  it('remove() notifies subscribers with a "deleted" event, then stops tracking it', () => {
    const registry = new Registry()
    registry.track('product', 1, { id: 1 }, async () => ({ id: 1 }))

    const listener = vi.fn()
    registry.subscribe('product', 1, listener)

    registry.remove('product', 1)

    expect(listener).toHaveBeenCalledWith({ type: 'deleted' })
    expect(registry.isTracked('product', 1)).toBe(false)
  })

  it('remove() on an untracked record is a no-op', () => {
    const registry = new Registry()
    expect(() => registry.remove('product', 999)).not.toThrow()
  })
})
