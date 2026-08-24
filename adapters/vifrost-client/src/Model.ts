import { client, getRegistry } from './config.js'
import { QueryBuilder } from './QueryBuilder.js'
import type { ModelConstructor } from './types.js'

/**
 * Base class generated `@vifrost/codegen` subclasses extend.
 *
 * @remarks
 * Every constructor argument is copied directly onto the instance, so a
 * generated subclass can declare its columns as typed fields
 * (`declare name: string`) and get correct property access without any
 * getter/setter boilerplate.
 */
export class Model {
  static resource = ''
  static primaryKey = 'id'
  /**
   * Permissive default for hand-written subclasses that don't set this — no
   * enforcement unless opted into. `@vifrost/codegen`-generated classes
   * always set this precisely from the schema.
   */
  static maxLimit: number | null = null;

  [attributeName: string]: unknown

  constructor(attributes: Record<string, unknown> = {}) {
    Object.assign(this, attributes)
  }

  private primaryKeyValue(): string | number {
    const modelConstructor = this.constructor as ModelConstructor
    const value = this[modelConstructor.primaryKey]
    if (value === undefined || value === null) {
      throw new Error(`${modelConstructor.name} instance has no "${modelConstructor.primaryKey}" set.`)
    }
    return value as string | number
  }

  /**
   * Wraps raw JSON in a Model instance and, if a {@link Registry} was passed
   * to {@link configure}, registers it so a future "this record changed"
   * event can find and refresh it. Every fetch path (find/all/query/
   * relations) goes through this, so tracking is automatic whenever the
   * registry is in use, and entirely skipped otherwise.
   */
  static instantiate<T extends Model>(this: ModelConstructor<T>, attributes: Record<string, unknown>): T {
    const instance = new this(attributes)
    const registry = getRegistry()
    const primaryKeyValue = attributes[this.primaryKey]
    if (registry && primaryKeyValue !== undefined && primaryKeyValue !== null) {
      registry.track(this.resource, primaryKeyValue as string | number, instance, () =>
        this.find(primaryKeyValue as string | number)
      )
    }
    return instance
  }

  /** Shorthand for `query().get()` — goes through the same `maxLimit` guard as any other collection fetch. */
  static async all<T extends Model>(this: ModelConstructor<T>): Promise<T[]> {
    return this.query().get()
  }

  static async find<T extends Model>(this: ModelConstructor<T>, id: string | number): Promise<T> {
    const row = await client().get<Record<string, unknown>>(`${this.resource}/${id}`)
    return this.instantiate(row)
  }

  static query<T extends Model>(this: ModelConstructor<T>): QueryBuilder<T> {
    return new QueryBuilder<T>(
      this.resource,
      async (path) => {
        const rows = await client().get<Record<string, unknown>[]>(path)
        return rows.map((row) => this.instantiate(row))
      },
      (id) => this.find(id),
      this.maxLimit
    )
  }

  /**
   * Creates (no primary key set) or updates (primary key set) this record.
   *
   * @remarks
   * Registers the saved value with the {@link Registry}, if one is
   * configured, the same way `find`/`all`/`query` do — so a create is
   * discoverable by a later `subscribe`, and an update notifies whoever
   * already subscribed to this record from an earlier fetch.
   */
  async save(): Promise<this> {
    const modelConstructor = this.constructor as ModelConstructor
    const primaryKeyValue = this[modelConstructor.primaryKey]
    const attributes: Record<string, unknown> = { ...this }

    const updatedRow =
      primaryKeyValue !== undefined && primaryKeyValue !== null
        ? await client().put<Record<string, unknown>>(`${modelConstructor.resource}/${primaryKeyValue}`, attributes)
        : await client().post<Record<string, unknown>>(modelConstructor.resource, attributes)

    Object.assign(this, updatedRow)

    const registry = getRegistry()
    const savedId = this[modelConstructor.primaryKey]
    if (registry && savedId !== undefined && savedId !== null) {
      registry.track(modelConstructor.resource, savedId as string | number, this, () =>
        modelConstructor.find(savedId as string | number)
      )
    }

    return this
  }

  /**
   * Deletes this record, notifying the {@link Registry} (if one is
   * configured) that it's gone.
   */
  async delete(): Promise<void> {
    const modelConstructor = this.constructor as ModelConstructor
    const primaryKeyValue = this.primaryKeyValue()
    await client().delete(`${modelConstructor.resource}/${primaryKeyValue}`)
    getRegistry()?.remove(modelConstructor.resource, primaryKeyValue)
  }

  /** One-hop collection relation: `GET {resource}/{id}/{relationName}`. */
  protected async toMany<R extends Model>(relationName: string, related: ModelConstructor<R>): Promise<R[]> {
    const modelConstructor = this.constructor as ModelConstructor
    const rows = await client().get<Record<string, unknown>[]>(
      `${modelConstructor.resource}/${this.primaryKeyValue()}/${relationName}`
    )
    return rows.map((row) => related.instantiate(row))
  }

  /**
   * A to-one relation (BelongsTo/HasOne/MorphOne).
   *
   * @remarks
   * Verified empirically against a real Vifrost API: this is served by the
   * exact same nested route as {@link Model.toMany} — Vifrost's generated
   * controller always calls `->get()`, regardless of the underlying Eloquent
   * relation type — so the response is a JSON array even for a to-one
   * relation. `GET /product/1/category` really does return `[{...}]`, not a
   * bare object. This takes the first element, or returns null if the
   * relation is empty (e.g. a nullable foreign key).
   */
  protected async toOne<R extends Model>(relationName: string, related: ModelConstructor<R>): Promise<R | null> {
    const relatedRows = await this.toMany(relationName, related)
    return relatedRows[0] ?? null
  }

  /**
   * One record within a collection relation, scoped by its own id:
   * `GET {resource}/{id}/{relationName}/{childId}`.
   */
  protected async toManyChild<R extends Model>(
    relationName: string,
    related: ModelConstructor<R>,
    childId: string | number
  ): Promise<R> {
    const modelConstructor = this.constructor as ModelConstructor
    const row = await client().get<Record<string, unknown>>(
      `${modelConstructor.resource}/${this.primaryKeyValue()}/${relationName}/${childId}`
    )
    return related.instantiate(row)
  }
}
