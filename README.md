# Fasttrack

Laravel library to make an API easily.

**This library is NOT stable yet. Use it at your own risk**

## Installation

Use the package manager [composer](https://getcomposer.org/) to install Fasttrack.

```bash
composer require asdfprah/fasttrack
```

## Usage

```bash
# to build a Laravel FormRequest named "StoreUserRequest" 
# for the \App\Http\Models\User model
php artisan fasttrack:request StoreUserRequest User

# to build a UserController, StoreUserRequest, UpdateUserRequest and routes
# for the \App\Http\Models\User model
php artisan fasttrack:api User

# to build a Controller, Requests and Routes for all models
php artisan fasttrack:api
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

### To Do

- [ ] Build missing models
- [x] Build FormRequest 
- [x] Build Controllers
- [ ] Optionally build JS classes to use the generated API (Something like [Coloquent](https://github.com/DavidDuwaer/Coloquent) or [vue-api-query](https://github.com/robsontenorio/vue-api-query))
- [ ] Improve code readability, honestly i just make it work for a side project
- [ ] Tests... 

## License
[MIT](https://choosealicense.com/licenses/mit/)