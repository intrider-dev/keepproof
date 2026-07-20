declare module 'swagger-ui-dist/swagger-ui-es-bundle.js' {
  interface SwaggerRequest {
    credentials?: RequestCredentials
    headers?: Record<string, string>
  }

  interface SwaggerUIOptions {
    dom_id: string
    url: string
    deepLinking?: boolean
    displayRequestDuration?: boolean
    persistAuthorization?: boolean
    presets?: unknown[]
    requestInterceptor?: (request: SwaggerRequest) => SwaggerRequest
  }

  interface SwaggerUIBundleFactory {
    (options: SwaggerUIOptions): unknown
    presets: { apis: unknown }
  }

  const SwaggerUIBundle: SwaggerUIBundleFactory

  export default SwaggerUIBundle
}
