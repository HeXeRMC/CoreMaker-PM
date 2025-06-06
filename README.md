# CoreMaker PM

**Modular plugin loader for PocketMine-MP.**  
CoreMaker allows you to organize, group and load plugins recursively from a folder structure, managing dependencies and permissions automatically.

---

## ❗ Requirements

- PocketMine-MP 5.0+
- [DevTools](https://poggit.pmmp.io/p/DevTools/)
- PHP extension: `ext-yaml` (`sudo apt install php-yaml`)

---

## 🚀 Features

- 🔄 **Recursive module loading**
- 📦 **Plugin dependency handling**
- 📛 **Permission registration**
- 🧠 **Avoids dependency loops**
- 🧹 **Clean, non-invasive loading**
- 🛠️ **Fully configurable logging**

---

## 📁 Folder Structure Example
    plugin_data/
    └── CoreMaker/
    └── plugins/
    ├── economy/
    │   └── EconomyAPI/
    ├── ranks/
    │   ├── PureChat/
    │   └── PurePerms/
    └── utility/
        └── Ping/

---

## ⚙️ Configuration

Located in:  
`plugin_data/CoreMaker/config.yml`

```yaml
logs:
  errors: true
  namespace_registered: false
  modules_loaded: true
  permissions_registered: false

