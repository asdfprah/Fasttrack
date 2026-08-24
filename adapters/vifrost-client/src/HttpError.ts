/**
 * Thrown by {@link HttpClient} for any non-2xx response.
 *
 * @remarks
 * Carries the parsed response body — e.g. Laravel's 422 validation payload,
 * `{ message, errors: { field: string[] } }` — so callers can branch on
 * {@link HttpError.status} and {@link HttpError.body} instead of re-parsing
 * the response themselves.
 */
export class HttpError extends Error {
  constructor(
    public readonly status: number,
    public readonly body: unknown
  ) {
    super(`HTTP ${status}`)
    this.name = 'HttpError'
  }
}
