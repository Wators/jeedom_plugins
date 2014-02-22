# Release 1.0.1 (in progress)

* Removed `actions` and `metrics` properties from the `module.json` of all modules.

* Module classes automatically loaded to the controller. Removed `modules` property from the `config.json`.

* Introduced `skip` property to the `module.json` which instructs AutomationController to skip module class loading.

* Introduced `autoloadPriority` property to the `module.json` which determines automatic module instantiation order (lower -- the sooner).

* Introduced `caps` property to the Virtual Device which allows to list device capabilities.

* Widgets moved to the new CommonWidgets module. Creation and registering custom widgets made possible.

* Module's templates and htdocs folders now resides in ./templates/<ModuleName> and ./htdocs/modules/<ModuleName>

* `config.json` existence checking on startup

* New configuration setting `vdevInfo` allows to set human-readable device names and tags

* Introduced `VirtualDevice.defaultDeviceTitle` method

* Widget's htdocs and Module's templates moded outside of the module's folder (for all modules)

* Introduced .caps vDev's property which contains device extended capabilities tags (take a look at BatterPolling module)

* Introduced className property in the widget's meta-description which allows to set custom widget className (essential to custom widgets)

* UI is now capable of showing widgets created and registered by the customer

# Release 1.0.0

(initial)
