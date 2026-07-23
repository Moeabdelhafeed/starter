# Introduction

Mobile app REST API for this Inertia starter (routes/api.php). Every request requires the X-API-TOKEN, X-Device-Id and X-Platform headers below — see IdentifyDevice / XApiTokenMiddlleware.

<aside>
    <strong>Base URL</strong>: <code>http://localhost</code>
</aside>

    This documentation is generated directly from `routes/api.php`, FormRequest validation rules, and controller docblocks — it is always in sync with the code, unlike the hand-maintained `Starter.postman_collection.json`.

    <aside>Every endpoint requires <code>X-API-TOKEN</code> (value of <code>APP_X_API_TOKEN</code> in <code>.env</code>), <code>X-Device-Id</code> (any UUID) and <code>X-Platform</code> (<code>web</code>/<code>ios</code>/<code>android</code>) headers — enforced globally by the <code>api</code> middleware group. <code>X-FCM-Token</code> is additionally required when platform is <code>ios</code> or <code>android</code>.</aside>

