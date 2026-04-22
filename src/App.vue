<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import draggable from 'vuedraggable'
import { Plus, SortAsc, Calendar, Trash2, Tag, X, Clock, Layers, Filter, Archive, HardDrive, User, LogOut, CheckCircle2, ArrowDown, HelpCircle, Menu, Sun, Moon } from 'lucide-vue-next'
import Editor from './components/Editor.vue'
import TodoItem from './components/TodoItem.vue'
import BackupModal from './components/BackupModal.vue'

const API_URL = import.meta.env.DEV ? 'http://localhost:8000/api' : '/api/index.php'
const AUTH_URL = import.meta.env.DEV ? 'http://localhost:8000/api/auth' : '/api/index.php/auth'

const isAuthenticated = ref(!!localStorage.getItem('todo_token'))
const loginData = ref({ username: 'frost0xx', password: '' })
const loginError = ref('')

// Axios config
axios.defaults.withCredentials = true
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('todo_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

axios.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      localStorage.removeItem('todo_token')
      isAuthenticated.value = false
    }
    return Promise.reject(err)
  }
)

const handleLogin = async () => {
  try {
    const res = await axios.post(`${AUTH_URL}/login`, loginData.value)
    localStorage.setItem('todo_token', res.data.token)
    isAuthenticated.value = true
    fetchData()
  } catch (err) {
    loginError.value = 'Login fehlgeschlagen. Bitte überprüfen Sie Ihre Daten.'
  }
}

const handleLogout = async () => {
  try { await axios.post(`${AUTH_URL}/logout`) } catch (err) {}
  localStorage.removeItem('todo_token')
  isAuthenticated.value = false
}

const todos = ref([])
const archivedTodos = ref([])
const isLoaded = ref(false)
const currentView = ref('main')
const showBackupModal = ref(false)

// Settings
const activeTags = ref([])
const isExclusive = ref(false)
const aggregation = ref('none')
const groupByTags = ref(false)
const activeSort = ref({ by: 'order', dir: 'asc' })
const showCompleted = ref(false)

const newTodo = ref({ name: '', description: '', targetDate: '', tags: [], status: 'offen' })
const newTodoTagInput = ref('')
const showNewForm = ref(false)
const searchQuery = ref('')
const searchExpanded = ref(false)
const searchInputRef = ref(null)
const mobileMenuOpen = ref(false)

const toggleSearch = () => {
  searchExpanded.value = !searchExpanded.value
  if (searchExpanded.value) {
    setTimeout(() => searchInputRef.value?.focus(), 100)
  }
}

const profileData = ref({ username: 'frost0xx', password: '' })
const profileStatus = ref('')
const showUserMenu = ref(false)

const fetchData = async () => {
  try {
    const [dataRes, settingsRes, archiveRes] = await Promise.all([
      axios.get(`${API_URL}/data`),
      axios.get(`${API_URL}/settings`),
      axios.get(`${API_URL}/archive`).catch(() => ({ data: { archivedTodos: [] } }))
    ])
    
    // Todos
    todos.value = (dataRes.data.todos || []).map(t => ({
      ...t,
      tags: t.tags || (t.category ? [t.category] : [])
    }))

    archivedTodos.value = archiveRes.data.archivedTodos || []

    // Settings
    const s = settingsRes.data
    if (s.activeTags) activeTags.value = s.activeTags
    if (s.isExclusive !== undefined) isExclusive.value = s.isExclusive
    if (s.aggregation) aggregation.value = s.aggregation
    if (s.groupByTags !== undefined) groupByTags.value = s.groupByTags
    if (s.activeSort) activeSort.value = s.activeSort
    if (s.showCompleted !== undefined) showCompleted.value = s.showCompleted

    isLoaded.value = true
  } catch (err) { console.error("Error fetching data", err) }
}

const updateCredentials = async () => {
  if (!profileData.value.username || !profileData.value.password) {
    profileStatus.value = 'Bitte beide Felder ausfüllen.'
    return
  }
  try {
    await axios.post(`${AUTH_URL}/update`, profileData.value)
    profileStatus.value = 'Erfolgreich gespeichert!'
    setTimeout(() => { profileStatus.value = ''; currentView.value = 'main' }, 1500)
  } catch (err) {
    profileStatus.value = 'Fehler beim Speichern.'
  }
}

const handleRevived = () => {
  fetchData()
}

const syncTodos = async () => {
  try { await axios.post(`${API_URL}/todos`, { todos: todos.value }) } 
  catch (err) { console.error("Error syncing todos", err) }
}

const syncArchive = async () => {
  try { await axios.post(`${API_URL}/archive`, { archivedTodos: archivedTodos.value }) } 
  catch (err) { console.error("Error syncing archive", err) }
}

const syncSettings = async () => {
  if (!isLoaded.value) return
  try {
    await axios.post(`${API_URL}/settings`, {
      activeTags: activeTags.value,
      isExclusive: isExclusive.value,
      aggregation: aggregation.value,
      groupByTags: groupByTags.value,
      activeSort: activeSort.value,
      showCompleted: showCompleted.value
    })
  } catch (err) { console.error("Error syncing settings", err) }
}

// Watch for setting changes
watch([activeTags, isExclusive, aggregation, groupByTags, activeSort, showCompleted], syncSettings, { deep: true })

const addTodo = () => {
  if (!newTodo.value.name) return
  const id = Date.now().toString()
  const order = todos.value.length > 0 ? Math.max(...todos.value.map(t => t.order)) + 1 : 0
  if (newTodoTagInput.value) {
    newTodo.value.tags = newTodoTagInput.value.split(',').map(s => s.trim()).filter(Boolean)
  }
  todos.value.push({ ...newTodo.value, id, order })
  newTodo.value = { name: '', description: '', targetDate: '', tags: [], status: 'offen' }
  newTodoTagInput.value = ''
  showNewForm.value = false
  syncTodos()
}

const deleteTodo = (id) => {
  const todoToArchive = todos.value.find(t => t.id === id)
  if (todoToArchive) {
    archivedTodos.value.push(todoToArchive)
    todos.value = todos.value.filter(t => t.id !== id)
    syncTodos()
    syncArchive()
  }
}

const reviveTodo = (id) => {
  const todoToRevive = archivedTodos.value.find(t => t.id === id)
  if (todoToRevive) {
    todos.value.push(todoToRevive)
    archivedTodos.value = archivedTodos.value.filter(t => t.id !== id)
    syncTodos()
    syncArchive()
  }
}

const updateTodo = (id, updates) => {
  const index = todos.value.findIndex(t => t.id === id)
  if (index !== -1) {
    todos.value[index] = { ...todos.value[index], ...updates }
    syncTodos()
  }
}

const onDragEnd = () => {
  if (aggregation.value !== 'none' || groupByTags.value || activeTags.value.length > 0 || activeSort.value.by !== 'order') return
  todos.value.forEach((todo, index) => { todo.order = index })
  syncTodos()
}

const getWeekNumber = (date) => {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
  const dayNum = d.getUTCDay() || 7
  d.setUTCDate(d.getUTCDate() + 4 - dayNum)
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1))
  return Math.ceil((((d - yearStart) / 86400000) + 1) / 7)
}

const getWeekRange = (date) => {
  const d = new Date(date)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1) // Monday
  const monday = new Date(d.setDate(diff))
  const sunday = new Date(monday)
  sunday.setDate(monday.getDate() + 6)
  
  const fmt = (d) => d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
  return `${fmt(monday)} - ${fmt(sunday)}`
}

const getGroupKey = (todo) => {
  if (!todo.targetDate) return 'Kein Datum'
  const date = new Date(todo.targetDate)
  if (isNaN(date.getTime())) return 'Kein Datum'
  if (aggregation.value === 'daily') return todo.targetDate
  if (aggregation.value === 'weekly') return `KW ${getWeekNumber(date)} (${date.getFullYear()})`
  if (aggregation.value === 'monthly') return date.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
  return 'Alle'
}

const getGroupLabel = (key) => {
  if (key === 'Kein Datum' || key === 'Alle') return key
  if (aggregation.value === 'daily') {
    const d = new Date(key)
    return d.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' })
  }
  if (aggregation.value === 'weekly') {
    const match = key.match(/KW (\d+) \((\d+)\)/)
    if (match) {
      const d = getDateFromWeekString(key)
      if (d) return `${key} [${getWeekRange(new Date(d))}]`
    }
  }
  return key
}

const filteredTodos = computed(() => {
  let result = [...todos.value]
  if (!showCompleted.value) {
    result = result.filter(t => t.status !== 'erledigt')
  }
  if (activeTags.value.length > 0) {
    if (isExclusive.value) result = result.filter(t => activeTags.value.every(tag => t.tags && t.tags.includes(tag)))
    else result = result.filter(t => activeTags.value.some(tag => t.tags && t.tags.includes(tag)))
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(t => 
      (t.name && t.name.toLowerCase().includes(q)) || 
      (t.description && t.description.toLowerCase().includes(q))
    )
  }
  return result
})

const groupedTodos = computed(() => {
  if (aggregation.value === 'none' && !groupByTags.value) return null
  let timeGroups = {}
  filteredTodos.value.forEach(todo => {
    const key = getGroupKey(todo)
    if (!timeGroups[key]) timeGroups[key] = []
    timeGroups[key].push(todo)
  })

  // Ensure current period is always shown if aggregation is active
  if (aggregation.value !== 'none') {
    const now = new Date()
    let currentKey = ''
    if (aggregation.value === 'daily') {
      const y = now.getFullYear()
      const m = String(now.getMonth() + 1).padStart(2, '0')
      const d = String(now.getDate()).padStart(2, '0')
      currentKey = `${y}-${m}-${d}`
    } else if (aggregation.value === 'weekly') {
      currentKey = `KW ${getWeekNumber(now)} (${now.getFullYear()})`
    } else if (aggregation.value === 'monthly') {
      currentKey = now.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
    }
    if (currentKey && !timeGroups[currentKey]) timeGroups[currentKey] = []
  }
  const finalGroups = []
  const sortedTimeKeys = Object.keys(timeGroups).sort((a, b) => {
    if (a === 'Kein Datum') return 1; if (b === 'Kein Datum') return -1; if (a === 'Alle') return -1; if (b === 'Alle') return 1
    return a.localeCompare(b)
  })
  sortedTimeKeys.forEach(timeKey => {
    const itemsInTimeGroup = timeGroups[timeKey]
    if (groupByTags.value) {
      const tagMap = {}
      itemsInTimeGroup.forEach(todo => {
        if (!todo.tags || todo.tags.length === 0) {
          if (!tagMap['Keine Tags']) tagMap['Keine Tags'] = []
          tagMap['Keine Tags'].push(todo)
        } else {
          todo.tags.forEach(tag => {
            if (!tagMap[tag]) tagMap[tag] = []
            tagMap[tag].push(todo)
          })
        }
      })
      const subGroups = Object.keys(tagMap).sort().map(tk => ({ key: tk, items: tagMap[tk] }))
      finalGroups.push({ key: timeKey, subGroups })
    } else {
      finalGroups.push({ key: timeKey, items: itemsInTimeGroup })
    }
  })
  return finalGroups
})

const sortedTodos = computed(() => {
  if (aggregation.value !== 'none' || groupByTags.value) return []
  let result = [...filteredTodos.value]
  if (activeSort.value.by === 'targetDate') {
    result.sort((a, b) => {
      if (!a.targetDate && !b.targetDate) return 0
      if (!a.targetDate) return 1; if (!b.targetDate) return -1
      const timeA = new Date(a.targetDate).getTime(); const timeB = new Date(b.targetDate).getTime()
      return activeSort.value.dir === 'asc' ? timeA - timeB : timeB - timeA
    })
  } else { result.sort((a, b) => a.order - b.order) }
  return result
})

const sortedArchived = computed(() => {
  return [...archivedTodos.value].sort((a, b) => {
    if (!a.targetDate && !b.targetDate) return 0
    if (!a.targetDate) return 1
    if (!b.targetDate) return -1
    return new Date(b.targetDate).getTime() - new Date(a.targetDate).getTime()
  })
})

const isCurrentPeriod = (key) => {
  if (key === 'Kein Datum' || key === 'Alle') return false
  const now = new Date()
  
  if (aggregation.value === 'daily') {
    const y = now.getFullYear()
    const m = String(now.getMonth() + 1).padStart(2, '0')
    const d = String(now.getDate()).padStart(2, '0')
    return key === `${y}-${m}-${d}`
  }
  if (aggregation.value === 'weekly') {
    return key === `KW ${getWeekNumber(now)} (${now.getFullYear()})`
  }
  if (aggregation.value === 'monthly') {
    return key === now.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
  }
  return false
}

const scrollToCurrent = () => {
  setTimeout(() => {
    const el = document.getElementById('group-current')
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }, 100)
}

watch(aggregation, (newVal) => {
  if (newVal !== 'none') {
    scrollToCurrent()
  }
})

const allTags = computed(() => {
  const tags = new Set()
  todos.value.forEach(t => t.tags?.forEach(tag => tags.add(tag)))
  return Array.from(tags).sort()
})

const toggleTagFilter = (tag) => {
  const index = activeTags.value.indexOf(tag)
  if (index === -1) activeTags.value.push(tag)
  else activeTags.value.splice(index, 1)
}

const resetAll = () => {
  aggregation.value = 'none'
  groupByTags.value = false
  activeTags.value = []
  isExclusive.value = false
  activeSort.value = { by: 'order', dir: 'asc' }
  showCompleted.value = false
}

const handleReorder = (newList) => {
  if (aggregation.value !== 'none' || groupByTags.value || activeTags.value.length > 0 || activeSort.value.by !== 'order') return
  todos.value = newList
  onDragEnd()
}

const getDateFromWeekString = (weekStr) => {
  const match = weekStr.match(/KW (\d+) \((\d+)\)/);
  if (!match) return null;
  const week = parseInt(match[1]);
  const year = parseInt(match[2]);
  
  const simple = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
  const dow = simple.getUTCDay();
  const ISOweekStart = simple;
  if (dow <= 4) {
    ISOweekStart.setUTCDate(simple.getUTCDate() - simple.getUTCDay() + 1);
  } else {
    ISOweekStart.setUTCDate(simple.getUTCDate() + 8 - simple.getUTCDay());
  }
  return ISOweekStart.toISOString().split('T')[0];
}

const getDateFromMonthString = (monthStr) => {
  const [monthName, year] = monthStr.split(' ');
  const months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  const monthIndex = months.indexOf(monthName);
  if (monthIndex === -1) return null;
  const d = new Date(Date.UTC(parseInt(year), monthIndex, 1));
  return d.toISOString().split('T')[0];
}

const handleGroupChange = (evt, groupKey) => {
  if (evt.added) {
    const todoId = evt.added.element.id;
    let newDate = null;
    
    if (aggregation.value === 'daily') {
      newDate = groupKey;
    } else if (aggregation.value === 'weekly') {
      newDate = getDateFromWeekString(groupKey);
    } else if (aggregation.value === 'monthly') {
      newDate = getDateFromMonthString(groupKey);
    } else {
      if (groupKey === 'Kein Datum' || groupKey === 'Alle') newDate = '';
    }
    
    if (newDate !== null) {
      updateTodo(todoId, { targetDate: newDate });
    }
  }
}

const handleImported = () => {
  showBackupModal.value = false
  fetchData()
}

const userMenuContainer = ref(null)

onMounted(() => {
  if (isAuthenticated.value) fetchData()
  
  // Close user menu on click away
  document.addEventListener('click', (e) => {
    if (userMenuContainer.value && !userMenuContainer.value.contains(e.target)) {
      showUserMenu.value = false
    }
  })
})

const isDarkMode = ref(localStorage.getItem('darkMode') === 'true')
const toggleDarkMode = () => {
  isDarkMode.value = !isDarkMode.value
  localStorage.setItem('darkMode', isDarkMode.value)
}

watch(isDarkMode, (val) => {
  if (val) {
    document.documentElement.classList.add('dark-mode')
  } else {
    document.documentElement.classList.remove('dark-mode')
  }
}, { immediate: true })
</script>

<template>
  <div class="app-container">
    <!-- 1. DESKTOP NAV -->
    <div class="top-bar card slim desktop-nav">
      <div class="top-bar-inner">
        <!-- Logo Area -->
        <div class="logo-area">
          <h1 class="logo">Aufgabenliste</h1>
          <button class="dark-mode-toggle" @click="toggleDarkMode" :title="isDarkMode ? 'Heller Modus' : 'Dunkler Modus'">
            <Sun v-if="isDarkMode" :size="14" />
            <Moon v-else :size="14" />
          </button>
        </div>

        <!-- Scrollable Filters -->
        <div class="scrollable-filters">
          <div class="control-item tags-filter-container">
            <Filter :size="14" style="flex-shrink: 0;" />
            <div class="tags-chips small-chips">
              <span v-for="tag in allTags" :key="tag" class="tag-chip" :class="{ active: activeTags.includes(tag) }" @click="toggleTagFilter(tag)">{{ tag }}</span>
            </div>
            <label class="toggle-label mini" v-if="activeTags.length > 1">
              <input type="checkbox" v-model="isExclusive" /> Exklusiv
            </label>
          </div>

          <div class="control-item tags-chips small-chips">
            <span class="tag-chip" :class="{ active: aggregation === 'daily' }" @click="aggregation = aggregation === 'daily' ? 'none' : 'daily'" title="Täglich">
              <Clock :size="12" /> Tag
            </span>
            <span class="tag-chip" :class="{ active: aggregation === 'weekly' }" @click="aggregation = aggregation === 'weekly' ? 'none' : 'weekly'" title="Wöchentlich">
              <Clock :size="12" /> Woche
            </span>
            <span class="tag-chip" :class="{ active: aggregation === 'monthly' }" @click="aggregation = aggregation === 'monthly' ? 'none' : 'monthly'" title="Monatlich">
              <Clock :size="12" /> Monat
            </span>
            <button v-if="aggregation !== 'none'" class="pure-button mini-btn secondary" @click="scrollToCurrent" title="Zum aktuellen Zeitraum springen" style="padding: 0 0.4rem; height: 1.5rem; margin-left: 0.2rem; color: var(--primary);">
              <ArrowDown :size="14" />
            </button>
          </div>

          <div v-if="aggregation === 'none'" class="control-item tags-chips small-chips">
            <span class="tag-chip" :class="{ active: activeSort.by === 'targetDate' }" @click="activeSort.by = activeSort.by === 'targetDate' ? 'order' : 'targetDate'" title="Nach Datum sortieren">
              <SortAsc :size="12" /> Datum
            </span>
            <button v-if="activeSort.by === 'targetDate'" class="pure-button mini-btn secondary" style="padding: 0 0.4rem; height: 1.5rem; margin-left: -0.2rem;" @click="activeSort.dir = activeSort.dir === 'asc' ? 'desc' : 'asc'">
              {{ activeSort.dir === 'asc' ? '↑' : '↓' }}
            </button>
          </div>

          <div class="control-item tags-chips small-chips">
            <span class="tag-chip" :class="{ active: groupByTags }" @click="groupByTags = !groupByTags" title="Nach Tags gruppieren">
              <Layers :size="12" /> Tags
            </span>
            <span class="tag-chip" :class="{ active: showCompleted }" @click="showCompleted = !showCompleted" title="Erledigte Aufgaben anzeigen">
              <CheckCircle2 :size="12" /> Erledigte
            </span>
          </div>
        </div>

        <!-- Fixed Actions -->
        <div class="fixed-actions">
          <button v-if="aggregation !== 'none' || groupByTags || activeTags.length || showCompleted" class="pure-button mini-btn secondary" @click="resetAll" title="Reset">
            <X :size="12" />
          </button>

          <button class="pure-button mini-btn secondary" @click="showBackupModal = true" title="Backup & Archiv">
            <HardDrive :size="14" />
          </button>

          <div class="user-menu-container" ref="userMenuContainer">
            <button class="pure-button mini-btn secondary" :class="{ 'admin-active': showUserMenu }" @click="showUserMenu = !showUserMenu" title="Admin Settings">
              <User :size="14" />
            </button>
            <div v-if="showUserMenu" class="user-dropdown card slim" @click.stop>
              <div class="dropdown-header">Admin Menu</div>
              <button class="dropdown-item" @click="currentView = 'profile'; showUserMenu = false">
                <User :size="12" /> Zugangsdaten
              </button>
              <button class="dropdown-item logout" @click="handleLogout(); showUserMenu = false">
                <LogOut :size="12" /> Abmelden
              </button>
            </div>
          </div>

          <button class="pure-button pure-button-primary mini-btn" @click="showNewForm = !showNewForm" title="Neu">
            <Plus :size="16" />
          </button>

          <div class="floating-search" :class="{ expanded: searchExpanded }">
            <input ref="searchInputRef" v-model="searchQuery" type="text" placeholder="Suchen..." class="expandable-input" :style="{ opacity: searchExpanded ? 1 : 0, pointerEvents: searchExpanded ? 'auto' : 'none' }" />
            <button class="search-icon-btn" @click="toggleSearch" title="Suchen">
              <HelpCircle :size="18" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. MOBILE NAV -->
    <div class="top-bar card slim mobile-nav">
      <div class="mobile-top-bar-inner">
        <button class="pure-button mini-btn secondary hamburger-btn" @click="mobileMenuOpen = true" title="Menü">
          <Menu :size="18" />
        </button>
        
        <div class="logo-group">
          <h1 class="logo mobile-logo">Aufgabenliste</h1>
          <button class="dark-mode-toggle mini" @click="toggleDarkMode">
            <Sun v-if="isDarkMode" :size="12" />
            <Moon v-else :size="12" />
          </button>
        </div>
        
        <div class="mobile-quick-actions">
          <button class="pure-button pure-button-primary mini-btn" @click="showNewForm = !showNewForm" title="Neu">
            <Plus :size="16" />
          </button>
          
          <button class="search-icon-btn mobile-search-btn" @click="toggleSearch" title="Suchen">
            <HelpCircle :size="18" />
          </button>
        </div>
      </div>
      
      <!-- Mobile Search Input (Visible only when expanded) -->
      <div v-if="searchExpanded" class="mobile-search-bar">
         <input ref="searchInputRef" v-model="searchQuery" type="text" placeholder="Suchen..." class="expandable-input mobile-input" />
         <button class="pure-button mini-btn secondary" @click="searchExpanded = false">
            <X :size="14" />
         </button>
      </div>
    </div>

    <!-- 3. MOBILE DRAWER OVERLAY -->
    <div class="mobile-drawer-overlay" :class="{ open: mobileMenuOpen }" @click="mobileMenuOpen = false">
      <div class="mobile-drawer-content card" @click.stop>
        <div class="drawer-header">
          <h2>Menü & Filter</h2>
          <button class="pure-button mini-btn secondary" @click="mobileMenuOpen = false"><X :size="16" /></button>
        </div>
        
        <div class="drawer-body">
           <!-- Filters duplicated from Desktop -->
           <div class="drawer-section">
             <h3>Filter & Tags</h3>
             <div class="tags-chips">
                <span v-for="tag in allTags" :key="tag" class="tag-chip" :class="{ active: activeTags.includes(tag) }" @click="toggleTagFilter(tag)">{{ tag }}</span>
             </div>
             <label class="toggle-label mini" v-if="activeTags.length > 1" style="margin-top: 0.5rem; display: inline-flex;">
                <input type="checkbox" v-model="isExclusive" /> Exklusiv
             </label>
           </div>
           
           <div class="drawer-section">
             <h3>Zeitraum</h3>
             <div class="tags-chips">
               <span class="tag-chip" :class="{ active: aggregation === 'daily' }" @click="aggregation = aggregation === 'daily' ? 'none' : 'daily'">Tag</span>
               <span class="tag-chip" :class="{ active: aggregation === 'weekly' }" @click="aggregation = aggregation === 'weekly' ? 'none' : 'weekly'">Woche</span>
               <span class="tag-chip" :class="{ active: aggregation === 'monthly' }" @click="aggregation = aggregation === 'monthly' ? 'none' : 'monthly'">Monat</span>
             </div>
             <button v-if="aggregation !== 'none'" class="pure-button mini-btn secondary drawer-btn" @click="scrollToCurrent(); mobileMenuOpen = false" style="margin-top: 0.5rem;">
               <ArrowDown :size="14" /> Zum aktuellen Zeitraum springen
             </button>
           </div>

           <div v-if="aggregation === 'none'" class="drawer-section">
             <h3>Sortierung</h3>
             <div class="tags-chips">
               <span class="tag-chip" :class="{ active: activeSort.by === 'targetDate' }" @click="activeSort.by = activeSort.by === 'targetDate' ? 'order' : 'targetDate'">Datum</span>
               <button v-if="activeSort.by === 'targetDate'" class="pure-button mini-btn secondary" style="padding: 0 0.4rem; height: 1.5rem;" @click="activeSort.dir = activeSort.dir === 'asc' ? 'desc' : 'asc'">
                 {{ activeSort.dir === 'asc' ? '↑ Aufsteigend' : '↓ Absteigend' }}
               </button>
             </div>
           </div>

           <div class="drawer-section">
             <h3>Ansicht</h3>
             <div class="tags-chips">
               <span class="tag-chip" :class="{ active: groupByTags }" @click="groupByTags = !groupByTags">Nach Tags gruppieren</span>
               <span class="tag-chip" :class="{ active: showCompleted }" @click="showCompleted = !showCompleted">Erledigte Aufgaben anzeigen</span>
             </div>
           </div>

           <div class="drawer-section drawer-actions">
             <button v-if="aggregation !== 'none' || groupByTags || activeTags.length || showCompleted" class="pure-button mini-btn secondary drawer-btn" @click="resetAll">
               <X :size="14" /> Alle Filter zurücksetzen
             </button>
             
             <button class="pure-button mini-btn secondary drawer-btn" @click="showBackupModal = true; mobileMenuOpen = false">
               <HardDrive :size="14" /> Backup & Archiv
             </button>

             <button class="pure-button mini-btn secondary drawer-btn" @click="currentView = 'profile'; mobileMenuOpen = false">
               <User :size="14" /> Zugangsdaten
             </button>

             <button class="pure-button mini-btn secondary drawer-btn" @click="handleLogout(); mobileMenuOpen = false">
               <LogOut :size="14" /> Abmelden
             </button>
           </div>
        </div>
      </div>
    </div>

    <div v-if="showNewForm" class="card new-todo-form">
      <form class="pure-form pure-form-stacked" @submit.prevent="addTodo">
        <fieldset>
          <legend>Neues Todo erstellen</legend>
          <div class="pure-g">
            <div class="pure-u-1 pure-u-md-1-2" style="padding-right: 1rem">
              <label>Name</label>
              <input v-model="newTodo.name" class="pure-u-1" type="text" maxlength="500" required />
            </div>
            <div class="pure-u-1 pure-u-md-1-4" style="padding-right: 1rem">
              <label>Zieldatum</label>
              <input v-model="newTodo.targetDate" class="pure-u-1" type="date" />
            </div>
            <div class="pure-u-1 pure-u-md-1-4">
              <label>Tags</label>
              <input v-model="newTodoTagInput" class="pure-u-1" placeholder="Tag eingeben oder wählen" />
              <div class="suggested-tags">
                <span v-for="tag in allTags" :key="tag" class="tag-chip mini" @click="newTodoTagInput = (newTodoTagInput ? newTodoTagInput + ', ' : '') + tag">+ {{ tag }}</span>
              </div>
            </div>
          </div>
          <div style="margin-top: 1rem">
            <label>Beschreibung</label>
            <Editor v-model="newTodo.description" />
          </div>
          <div style="margin-top: 1rem; text-align: right;">
            <button type="button" class="pure-button" style="margin-right: 0.5rem" @click="showNewForm = false">Abbrechen</button>
            <button type="submit" class="pure-button pure-button-primary">Speichern</button>
          </div>
        </fieldset>
      </form>
    </div>

    <div v-if="currentView === 'main'" class="todo-list">
      <template v-if="aggregation === 'none' && !groupByTags">
        <draggable :model-value="sortedTodos" item-key="id" handle=".drag-handle" :animation="200" ghost-class="ghost" @update:model-value="handleReorder" :disabled="activeTags.length > 0 || activeSort.by !== 'order'">
          <template #item="{ element }">
            <TodoItem :todo="element" :all-tags="allTags" :can-drag="activeTags.length === 0 && activeSort.by === 'order'" @update="(updates) => updateTodo(element.id, updates)" @delete="deleteTodo(element.id)" />
          </template>
        </draggable>
        <div v-if="sortedTodos.length === 0" class="empty-state">Keine Aufgaben gefunden.</div>
      </template>

      <template v-else>
        <div v-for="group in groupedTodos" :key="group.key" class="todo-group">
          <div v-if="group.key !== 'Alle'" class="group-header" :class="{ 'current-period': isCurrentPeriod(group.key) }" :id="isCurrentPeriod(group.key) ? 'group-current' : ''">
            <Calendar :size="16" /> {{ getGroupLabel(group.key) }}
            <span v-if="isCurrentPeriod(group.key)" class="badge current-badge">Aktuell</span>
          </div>
          <div v-if="groupByTags" class="tag-subgroups">
            <div v-for="sub in group.subGroups" :key="sub.key" class="tag-group">
              <div class="sub-header"><Tag :size="12" /> {{ sub.key }}</div>
              <div class="group-items">
                <draggable :model-value="sub.items" group="todos" item-key="id" handle=".drag-handle" :animation="200" ghost-class="ghost" @change="(evt) => handleGroupChange(evt, group.key)" :disabled="activeSort.by !== 'order' || activeTags.length > 0">
                  <template #item="{ element }">
                    <TodoItem :todo="element" :all-tags="allTags" :can-drag="activeSort.by === 'order' && activeTags.length === 0" @update="(updates) => updateTodo(element.id, updates)" @delete="deleteTodo(element.id)" />
                  </template>
                </draggable>
              </div>
            </div>
          </div>
          <div v-else class="group-items">
            <draggable :model-value="group.items" group="todos" item-key="id" handle=".drag-handle" :animation="200" ghost-class="ghost" @change="(evt) => handleGroupChange(evt, group.key)" :disabled="activeSort.by !== 'order' || activeTags.length > 0">
              <template #item="{ element }">
                <TodoItem :todo="element" :all-tags="allTags" :can-drag="activeSort.by === 'order' && activeTags.length === 0" @update="(updates) => updateTodo(element.id, updates)" @delete="deleteTodo(element.id)" />
              </template>
            </draggable>
          </div>
        </div>
        <div v-if="groupedTodos.length === 0" class="empty-state">Keine Aufgaben gefunden.</div>
      </template>
    </div>

    <div v-else-if="currentView === 'profile'" class="todo-list">
      <div class="card slim" style="max-width: 400px; margin: 0 auto; padding: 2rem;">
        <h2 class="text-center"><User :size="24" /> Zugangsdaten ändern</h2>
        <div class="pure-form pure-form-stacked">
          <label>Neuer Benutzername</label>
          <input v-model="profileData.username" type="text" class="pure-input-1" placeholder="Benutzername" />
          
          <label>Neues Passwort</label>
          <input v-model="profileData.password" type="password" class="pure-input-1" placeholder="Passwort" />
          
          <p v-if="profileStatus" :class="{ 'error-text': profileStatus.includes('Fehler'), 'text-success': profileStatus.includes('Erfolgreich') }" class="status-msg text-center">
            {{ profileStatus }}
          </p>
          
          <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
            <button class="pure-button pure-button-primary pure-input-1" @click="updateCredentials">Speichern</button>
            <button class="pure-button pure-input-1" @click="currentView = 'main'">Abbrechen</button>
          </div>
        </div>
      </div>
    </div>

    <BackupModal :show="showBackupModal" :all-tags="allTags" @close="showBackupModal = false" @imported="handleImported" @revived="handleRevived" />

    <!-- Login Modal -->
    <div v-if="!isAuthenticated" class="modal-overlay login-overlay">
      <div class="modal-content card slim login-card">
        <h2 class="text-center">Anmelden</h2>
        <div class="login-form">
          <div class="pure-form pure-form-stacked">
            <label>Benutzername</label>
            <input v-model="loginData.username" type="text" class="pure-input-1" />
            
            <label>Passwort</label>
            <input v-model="loginData.password" type="password" class="pure-input-1" @keyup.enter="handleLogin" />
            
            <p v-if="loginError" class="error-text">{{ loginError }}</p>
            
            <button class="pure-button pure-button-primary pure-input-1" @click="handleLogin">
              Anmelden
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.top-bar { padding: 0.3rem 0.75rem; margin-bottom: 0.5rem; position: sticky; top: 0.5rem; z-index: 1000; overflow: visible; }
.top-bar-inner { display: flex; align-items: center; gap: 0.5rem; width: 100%; min-height: 2rem; justify-content: space-between; }
.logo-area { flex-shrink: 0; }
.logo { font-size: 0.95rem; margin: 0; line-height: 2rem; white-space: nowrap; font-weight: 800; color: var(--primary); }

.scrollable-filters { flex: 1 1 auto; display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
.scrollable-filters::-webkit-scrollbar { display: none; }

.fixed-actions { flex-shrink: 0; display: flex; align-items: center; gap: 0.5rem; position: relative; padding-right: 36px; height: 100%; }

.control-item { display: flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; color: #4b5563; white-space: nowrap; flex-shrink: 0; height: 1.8rem; }
.tags-filter-container { flex: 0 1 auto; max-width: 45%; display: flex; align-items: center; gap: 0.3rem; }
.small-chips { gap: 0.15rem !important; overflow-x: auto; white-space: nowrap; scrollbar-width: none; display: flex; align-items: center; height: 100%; }
.small-chips::-webkit-scrollbar { display: none; }
.small-chips .tag-chip { font-size: 0.6rem; padding: 0.05rem 0.35rem; border-radius: 2rem; flex-shrink: 0; display: flex; align-items: center; height: 1.4rem; }

.floating-search { position: absolute; right: 0; top: 50%; transform: translateY(-50%); display: flex; align-items: center; background: white; border-radius: 2rem; border: 1px solid var(--border-color); overflow: hidden; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); width: 32px; height: 32px; z-index: 100; box-sizing: border-box; }
.floating-search.expanded { width: 220px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-color: #3b82f6; }
.search-icon-btn { position: absolute; right: 0; top: 0; width: 30px; height: 30px; border-radius: 50%; background: transparent; border: none; display: flex; align-items: center; justify-content: center; color: #6b7280; cursor: pointer; padding: 0; flex-shrink: 0; outline: none; }
.expandable-input { width: 100%; border: none; background: transparent; padding: 0 30px 0 0.8rem; font-size: 0.85rem; outline: none; color: #374151; transition: opacity 0.2s; box-sizing: border-box; height: 100%; }

.toggle-label { cursor: pointer; display: flex; align-items: center; gap: 0.2rem; font-weight: 500; flex-shrink: 0; height: 100%; }
.toggle-label.mini { font-size: 0.65rem; color: var(--primary); background: #eff6ff; padding: 0 0.4rem; border-radius: 2rem; height: 1.4rem; }
.minimal-select { border: 1px solid var(--border-color); background: white; font-size: 0.7rem; color: #1f2937; padding: 0.1rem 0.25rem; border-radius: 2rem; height: 1.6rem; }
.mini-btn { padding: 0 0.4rem; font-size: 0.75rem; border-radius: 2rem !important; min-width: 2rem; display: flex; justify-content: center; align-items: center; height: 1.6rem; box-sizing: border-box; }
.mini-btn.secondary { background: #f3f4f6; color: #4b5563; border: 1px solid var(--border-color); }
.tag-chip { padding: 0.1rem 0.4rem; background: #f3f4f6; border-radius: 2rem; cursor: pointer; white-space: nowrap; transition: all 0.2s; border: 1px solid transparent; }
.tag-chip:hover { background: #e5e7eb; }
.tag-chip.active { background: #3b82f6; color: white; border-color: #2563eb; }
.suggested-tags { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.25rem; }
.tag-chip.mini { font-size: 0.6rem; padding: 0.05rem 0.25rem; color: #6b7280; }
.ghost { opacity: 0.5; background: #eef2ff; }
.todo-list { display: flex; flex-direction: column; gap: 0.3rem; }
.empty-state { text-align: center; padding: 2rem; color: #9ca3af; font-style: italic; font-size: 0.9rem; }
.todo-group { margin-bottom: 0.75rem; }
.group-header { padding: 0.5rem 1rem; background: var(--tag-bg); font-weight: 600; font-size: 0.95rem; color: var(--text-heading); display: flex; align-items: center; gap: 0.5rem; border-radius: 0.5rem; margin-bottom: 0.5rem; }
.group-header.current-period { background: var(--header-bg); border-left: 4px solid var(--primary); color: var(--primary); }
.current-badge { background: var(--primary); color: white; margin-left: 0.5rem; font-size: 0.7rem; padding: 0.1rem 0.5rem; border-radius: 2rem; }
.sub-header { font-size: 0.6rem; color: var(--text-muted); font-weight: 600; padding: 0.2rem 0.75rem; background: transparent; display: flex; align-items: center; gap: 0.25rem; border-bottom: none; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.5rem; margin-bottom: 0.1rem; }
.tag-group { margin-left: 0.2rem; border-left: 1px solid var(--border-color); }
.group-items { display: flex; flex-direction: column; gap: 0.25rem; padding: 0.1rem 0.3rem; }

/* Mobile specific classes hidden on desktop */
.mobile-nav { display: none; }
.mobile-drawer-overlay { display: none; }

/* Desktop-only styles - no changes needed here for desktop */

@media (max-width: 1024px) {
  .top-bar-inner {
    flex-wrap: wrap;
  }
  .scrollable-filters {
    order: 3;
    width: 100%;
    justify-content: flex-start;
  }
}

@media (max-width: 768px) {
  /* HIDE DESKTOP NAV COMPLETELY */
  .desktop-nav { display: none !important; }
  
  /* SHOW MOBILE NAV */
  .mobile-nav { 
    display: block !important; 
    position: -webkit-sticky !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 1000 !important;
    padding: 0.4rem 0.75rem; 
    background: white;
  }
  .mobile-top-bar-inner { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; }
  
  .hamburger-btn { background: transparent; border: none; padding: 0.3rem; display: flex; align-items: center; justify-content: center; }
  .mobile-logo { font-size: 1.1rem; font-weight: 800; color: var(--primary); margin: 0; position: absolute; left: 50%; transform: translateX(-50%); }
  
  .mobile-quick-actions { display: flex; align-items: center; gap: 0.5rem; }
  .mobile-search-btn { position: relative; width: 32px; height: 32px; background: #f3f4f6; color: #4b5563; }
  
  .mobile-search-bar { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; padding: 0.2rem 0; animation: slideDown 0.2s ease-out; }
  .mobile-input { background: #f9fafb; border: 1px solid var(--border-color); border-radius: 2rem; padding: 0.4rem 1rem; flex: 1; height: 2rem; }
  
  @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

  /* MOBILE DRAWER OVERLAY */
  .mobile-drawer-overlay {
    display: block; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 2000;
    opacity: 0; pointer-events: none; transition: opacity 0.3s;
  }
  .mobile-drawer-overlay.open { opacity: 1; pointer-events: auto; }
  
  .mobile-drawer-content {
    position: absolute; top: 0; left: -100%; bottom: 0; width: 85%; max-width: 320px;
    background: white; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    display: flex; flex-direction: column; transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 0;
  }
  .mobile-drawer-overlay.open .mobile-drawer-content { left: 0; }
  
  .drawer-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--border-color); }
  .drawer-header h2 { margin: 0; font-size: 1.2rem; color: #1f2937; }
  
  .drawer-body { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 1.5rem; }
  
  .drawer-section h3 { margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
  .drawer-section .tags-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; overflow: visible; }
  .drawer-section .tag-chip { font-size: 0.85rem; padding: 0.4rem 0.8rem; height: auto; display: inline-block; }
  
  .drawer-actions { display: flex; flex-direction: column; gap: 0.5rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; }
  .drawer-btn { justify-content: flex-start; padding: 0.75rem 1rem; font-size: 0.95rem; height: auto; gap: 0.75rem; background: #f9fafb; border: 1px solid var(--border-color); width: 100%; }
  
  .new-todo-form {
    position: -webkit-sticky !important;
    position: sticky !important;
    top: 3rem !important;
    z-index: 900 !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    max-height: 80vh;
    overflow-y: auto;
  }

  .empty-state {
    padding: 1.5rem 0.75rem;
  }
}

/* Auth Styles */
.user-menu-container {
  position: relative;
}

.admin-active {
  background: #3b82f6 !important;
  color: white !important;
}

.user-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 0.5rem;
  width: 160px;
  z-index: 10000;
  padding: 0.5rem !important;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
  border: 1px solid #e5e7eb;
  background: white;
}

.dropdown-header {
  font-size: 0.65rem;
  font-weight: bold;
  color: #9ca3af;
  padding: 0.25rem 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.dropdown-item {
  background: none;
  border: none;
  padding: 0.6rem 0.75rem;
  font-size: 0.8rem;
  text-align: left;
  cursor: pointer;
  border-radius: 0.375rem;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  color: #374151;
  width: 100%;
  transition: all 0.1s;
}

.dropdown-item:hover {
  background: #eff6ff;
  color: #2563eb;
}

.dropdown-item.logout:hover {
  color: #ef4444;
  background: #fef2f2;
}

.login-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(5px);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.login-card {
  width: 350px;
  max-width: 90%;
  padding: 2rem;
}

.error-text {
  color: #ff4444;
  font-size: 0.8rem;
  margin: 0.5rem 0;
}

.hint-text {
  font-size: 0.75rem;
  color: #888;
  margin-top: 1rem;
}

.text-success {
  color: #10b981;
  font-size: 0.8rem;
  margin: 0.5rem 0;
}

.text-center { text-align: center; }
.mt-2 { margin-top: 0.5rem; }
</style>
