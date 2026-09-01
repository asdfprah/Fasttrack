import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { guardUnloadedRelations } from '../src/RelationGuard.js'

class FakeProduct {
  id = 1
  category_id = 9
  category(): Promise<{ id: number; name: string } | null> {
    return Promise.resolve({ id: 9, name: 'Widgets' })
  }
  comments(): Promise<unknown[]> {
    return Promise.resolve([])
  }
}

let warnSpy: ReturnType<typeof vi.spyOn>

beforeEach(() => {
  warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})
})

afterEach(() => {
  warnSpy.mockRestore()
})

describe('guardUnloadedRelations', () => {
  it('returns the instance untouched when nothing is unloaded', () => {
    const instance = new FakeProduct()

    expect(guardUnloadedRelations(instance, [], 'warn')).toBe(instance)
  })

  it('lets a normal call through with no warning — the intended, correct usage', async () => {
    const guarded = guardUnloadedRelations(new FakeProduct(), ['category'], 'warn')

    await expect(guarded.category()).resolves.toEqual({ id: 9, name: 'Widgets' })
    expect(warnSpy).not.toHaveBeenCalled()
  })

  it('warns and returns undefined when a property is read off an unloaded relation instead of calling it', () => {
    const guarded = guardUnloadedRelations(new FakeProduct(), ['category'], 'warn')

    const name = (guarded.category as unknown as { name: string }).name

    expect(name).toBeUndefined()
    expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('"category" hasn\'t been loaded'))
  })

  it('throws instead of warning when mode is "error"', () => {
    const guarded = guardUnloadedRelations(new FakeProduct(), ['category'], 'error')

    expect(() => (guarded.category as unknown as { name: string }).name).toThrow(/hasn't been loaded/)
    expect(warnSpy).not.toHaveBeenCalled()
  })

  it('does not warn on safe internal probes like "then"', () => {
    const guarded = guardUnloadedRelations(new FakeProduct(), ['category'], 'warn')

    expect((guarded.category as unknown as { then?: unknown }).then).toBeUndefined()
    expect(warnSpy).not.toHaveBeenCalled()
  })

  it('only guards the relations actually missing — a loaded one on the same instance stays untouched', () => {
    // Same shape as a real partial .with('category') fetch: `category` came
    // back eager-loaded as real data (an own property shadowing the method),
    // `comments` didn't, so only `comments` should be in unloadedRelationNames.
    const instance = Object.assign(new FakeProduct(), { category: { id: 9, name: 'Widgets' } })
    const guarded = guardUnloadedRelations(instance, ['comments'], 'warn')

    expect(guarded.category).toEqual({ id: 9, name: 'Widgets' }) // loaded — plain data, no warning
    expect(warnSpy).not.toHaveBeenCalled()

    const commentsLength = (guarded.comments as unknown as { length: number }).length // unloaded — still the method
    expect(commentsLength).toBeUndefined()
    expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('"comments" hasn\'t been loaded'))
  })
})
