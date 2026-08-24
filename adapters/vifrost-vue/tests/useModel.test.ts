import { Model, Registry, configure, resetClient } from '@vifrost/client'
import { effectScope, nextTick, ref } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useModel } from '../src/useModel.js'

class Category extends Model {
  static resource = 'category'
  declare id: number
  declare name: string
}

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } })
}

let fetchMock: ReturnType<typeof vi.fn>
let scope: ReturnType<typeof effectScope>

beforeEach(() => {
  fetchMock = vi.fn()
  scope = effectScope()
})

afterEach(() => {
  scope.stop()
  resetClient()
})

describe('useModel (no registry configured)', () => {
  beforeEach(() => {
    configure({ baseUrl: 'https://api.test', fetch: fetchMock as unknown as typeof fetch })
  })

  it('fetches on creation and exposes isLoading/data transitions', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ id: 1, name: 'Widgets' }))

    const { data, isLoading } = scope.run(() => useModel(Category, 1))!

    expect(isLoading.value).toBe(true)
    expect(data.value).toBeNull()

    await vi.waitFor(() => expect(isLoading.value).toBe(false))

    expect(data.value).toBeInstanceOf(Category)
    expect(data.value?.name).toBe('Widgets')
  })

  it('sets error and leaves data null when the fetch rejects', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ message: 'Not Found' }, 404))

    const { data, error, isLoading } = scope.run(() => useModel(Category, 999))!

    await vi.waitFor(() => expect(isLoading.value).toBe(false))

    expect(data.value).toBeNull()
    expect(error.value).toBeInstanceOf(Error)
  })

  it('re-fetches when a reactive id ref changes', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Widgets' }))
      .mockResolvedValueOnce(jsonResponse({ id: 2, name: 'Gizmos' }))

    const id = ref(1)
    const { data, isLoading } = scope.run(() => useModel(Category, id))!

    await vi.waitFor(() => expect(isLoading.value).toBe(false))
    expect(data.value?.name).toBe('Widgets')

    id.value = 2
    await nextTick()
    await vi.waitFor(() => expect(isLoading.value).toBe(false))

    expect(fetchMock).toHaveBeenCalledWith('https://api.test/category/2', expect.any(Object))
    expect(data.value?.name).toBe('Gizmos')
  })

  it('refetch() re-runs the fetch on demand', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Widgets' }))
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Widgets (renamed)' }))

    const { data, refetch, isLoading } = scope.run(() => useModel(Category, 1))!
    await vi.waitFor(() => expect(isLoading.value).toBe(false))

    await refetch()

    expect(data.value?.name).toBe('Widgets (renamed)')
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })
})

describe('useModel (with a configured registry)', () => {
  let registry: Registry

  beforeEach(() => {
    registry = new Registry()
    configure({ baseUrl: 'https://api.test', fetch: fetchMock as unknown as typeof fetch, registry })
  })

  it('stays in sync when the record is updated elsewhere in the app', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Widgets' })) // useModel's own find(1)
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Widgets' })) // the test's own find(1)
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Widgets (renamed)' })) // save()'s PUT

    const { data, isLoading } = scope.run(() => useModel(Category, 1))!
    await vi.waitFor(() => expect(isLoading.value).toBe(false))
    expect(data.value?.name).toBe('Widgets')

    // Some unrelated part of the app re-fetches/updates the same record.
    const sameCategory = await Category.find(1)
    sameCategory.name = 'Widgets (renamed)'
    await sameCategory.save()

    await vi.waitFor(() => expect(data.value?.name).toBe('Widgets (renamed)'))
  })

  it('sets data to null when the record is deleted elsewhere in the app', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Widgets' }))
      .mockResolvedValueOnce(jsonResponse({ id: 1, name: 'Widgets' }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))

    const { data, isLoading } = scope.run(() => useModel(Category, 1))!
    await vi.waitFor(() => expect(isLoading.value).toBe(false))

    const sameCategory = await Category.find(1)
    await sameCategory.delete()

    await vi.waitFor(() => expect(data.value).toBeNull())
  })

  it('unsubscribes from the registry when the scope is disposed', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ id: 1, name: 'Widgets' }))

    const { isLoading } = scope.run(() => useModel(Category, 1))!
    await vi.waitFor(() => expect(isLoading.value).toBe(false))

    expect(registry.isTracked('category', 1)).toBe(true)
    scope.stop()

    // No listener left to throw/act on further events for this id.
    expect(() => registry.track('category', 1, { id: 1, name: 'irrelevant' }, async () => ({ id: 1 }))).not.toThrow()
  })
})
