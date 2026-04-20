<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import { StarterKit } from '@tiptap/starter-kit'
import { Underline } from '@tiptap/extension-underline'
import { TextAlign } from '@tiptap/extension-text-align'
import { TextStyle } from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import { Placeholder } from '@tiptap/extension-placeholder'
import { Bold, Italic, Underline as UnderlineIcon, Strikethrough, Heading1, Heading2, Heading3, List, ListOrdered, Quote, Code, AlignLeft, AlignCenter, AlignRight, AlignJustify, Eraser } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Schreibe etwas...',
  }
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
    Underline,
    TextStyle,
    Color,
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
    Placeholder.configure({
      placeholder: props.placeholder,
    }),
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  },
})

watch(() => props.modelValue, (value) => {
  if (!editor.value) return
  const isSame = editor.value.getHTML() === value
  if (isSame) {
    return
  }
  editor.value.commands.setContent(value, false)
})

onBeforeUnmount(() => {
  if (editor.value) {
    editor.value.destroy()
  }
})
</script>

<template>
  <div class="editor-container" v-if="editor">
    <div class="editor-toolbar">
      <div class="toolbar-group">
        <button type="button" @click="editor.chain().focus().toggleBold().run()" :class="{ 'is-active': editor.isActive('bold') }" title="Fett">
          <Bold :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().toggleItalic().run()" :class="{ 'is-active': editor.isActive('italic') }" title="Kursiv">
          <Italic :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().toggleUnderline().run()" :class="{ 'is-active': editor.isActive('underline') }" title="Unterstrichen">
          <UnderlineIcon :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().toggleStrike().run()" :class="{ 'is-active': editor.isActive('strike') }" title="Durchgestrichen">
          <Strikethrough :size="16" />
        </button>
      </div>

      <div class="divider"></div>

      <div class="toolbar-group">
        <button type="button" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 1 }) }" title="Überschrift 1">
          <Heading1 :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }" title="Überschrift 2">
          <Heading2 :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }" title="Überschrift 3">
          <Heading3 :size="16" />
        </button>
      </div>

      <div class="divider"></div>

      <div class="toolbar-group">
        <button type="button" @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'is-active': editor.isActive('bulletList') }" title="Aufzählungsliste">
          <List :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'is-active': editor.isActive('orderedList') }" title="Nummerierte Liste">
          <ListOrdered :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().toggleBlockquote().run()" :class="{ 'is-active': editor.isActive('blockquote') }" title="Zitat">
          <Quote :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().toggleCodeBlock().run()" :class="{ 'is-active': editor.isActive('codeBlock') }" title="Code Block">
          <Code :size="16" />
        </button>
      </div>

      <div class="divider"></div>

      <div class="toolbar-group">
        <button type="button" @click="editor.chain().focus().setTextAlign('left').run()" :class="{ 'is-active': editor.isActive({ textAlign: 'left' }) }" title="Linksbündig">
          <AlignLeft :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().setTextAlign('center').run()" :class="{ 'is-active': editor.isActive({ textAlign: 'center' }) }" title="Zentriert">
          <AlignCenter :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().setTextAlign('right').run()" :class="{ 'is-active': editor.isActive({ textAlign: 'right' }) }" title="Rechtsbündig">
          <AlignRight :size="16" />
        </button>
        <button type="button" @click="editor.chain().focus().setTextAlign('justify').run()" :class="{ 'is-active': editor.isActive({ textAlign: 'justify' }) }" title="Blocksatz">
          <AlignJustify :size="16" />
        </button>
      </div>

      <div class="divider"></div>

      <div class="toolbar-group">
        <input type="color" @input="editor.chain().focus().setColor($event.target.value).run()" :value="editor.getAttributes('textStyle').color || '#000000'" class="color-picker" title="Textfarbe" />
      </div>

      <div class="divider"></div>

      <div class="toolbar-group">
        <button type="button" @click="editor.chain().focus().clearNodes().unsetAllMarks().run()" title="Formatierung entfernen">
          <Eraser :size="16" />
        </button>
      </div>
    </div>
    
    <editor-content :editor="editor" class="editor-content" />
  </div>
</template>

<style scoped>
.editor-container {
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 0.5rem;
  background: white;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  padding: 0.5rem;
  background: #f9fafb;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
  align-items: center;
}

.toolbar-group {
  display: flex;
  gap: 0.15rem;
}

.editor-toolbar button {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 0.25rem;
  padding: 0.35rem;
  cursor: pointer;
  color: #4b5563;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.editor-toolbar button:hover {
  background: #e5e7eb;
  color: #111827;
}

.editor-toolbar button.is-active {
  background: #dbeafe;
  color: #2563eb;
}

.divider {
  width: 1px;
  height: 1.5rem;
  background: #d1d5db;
  margin: 0 0.25rem;
}

.color-picker {
  border: none;
  background: transparent;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  cursor: pointer;
  border-radius: 0.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.color-picker::-webkit-color-swatch-wrapper {
  padding: 0;
}

.color-picker::-webkit-color-swatch {
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
}

.editor-content {
  padding: 1rem;
  min-height: 150px;
  max-height: 400px;
  overflow-y: auto;
  cursor: text;
}

/* Tiptap styles */
:deep(.ProseMirror) {
  outline: none;
  min-height: 100%;
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  float: left;
  color: #9ca3af;
  pointer-events: none;
  height: 0;
}

:deep(.ProseMirror blockquote) {
  border-left: 3px solid #e5e7eb;
  padding-left: 1rem;
  margin-left: 0;
  color: #4b5563;
  font-style: italic;
}

:deep(.ProseMirror pre) {
  background: #1f2937;
  color: #f8f8f2;
  font-family: 'JetBrainsMono', monospace;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  white-space: pre-wrap;
}

:deep(.ProseMirror code) {
  font-size: 0.9em;
  background: rgba(97, 97, 97, 0.1);
  color: #616161;
  padding: 0.2em 0.4em;
  border-radius: 0.25em;
}

:deep(.ProseMirror ul),
:deep(.ProseMirror ol) {
  padding: 0 1rem;
  margin: 0.5rem 0;
}

:deep(.ProseMirror p) {
  margin-top: 0;
  margin-bottom: 0.5rem;
}

/* Responsive Design for Tablets and Smartphones */
@media (max-width: 768px) {
  .editor-toolbar {
    gap: 0.1rem;
    padding: 0.3rem;
  }

  .toolbar-group {
    gap: 0.08rem;
  }

  .editor-toolbar button {
    padding: 0.25rem;
    font-size: 0.8rem;
  }

  .divider {
    display: none;
  }

  .color-picker {
    width: 1.5rem;
    height: 1.5rem;
  }

  .editor-content {
    padding: 0.75rem;
    min-height: 120px;
    max-height: 300px;
  }
}

@media (max-width: 480px) {
  .editor-toolbar {
    gap: 0.08rem;
    padding: 0.25rem;
  }

  .toolbar-group {
    gap: 0.05rem;
  }

  .editor-toolbar button {
    padding: 0.2rem;
    font-size: 0.75rem;
  }

  .color-picker {
    width: 1.3rem;
    height: 1.3rem;
  }

  .editor-content {
    padding: 0.5rem;
    min-height: 100px;
    max-height: 250px;
    font-size: 0.9rem;
  }

  :deep(.ProseMirror blockquote) {
    padding-left: 0.75rem;
  }

  :deep(.ProseMirror pre) {
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
  }
}
</style>
