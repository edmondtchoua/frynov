import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Icon from '@/shared/ui/Icon.vue'

describe('Icon', () => {
  it('renders the registered primitives for a known name', () => {
    const w = mount(Icon, { props: { name: 'plus' } })
    expect(w.find('svg').exists()).toBe(true)
    expect(w.findAll('path').length).toBe(2)            // plus = two strokes
  })

  it('supports circle primitives (search)', () => {
    const w = mount(Icon, { props: { name: 'search' } })
    expect(w.find('circle').exists()).toBe(true)
    expect(w.findAll('path').length).toBe(1)
  })

  it('is decorative by default (aria-hidden, no role)', () => {
    const svg = mount(Icon, { props: { name: 'plus' } }).find('svg')
    expect(svg.attributes('aria-hidden')).toBe('true')
    expect(svg.attributes('role')).toBeUndefined()
  })

  it('becomes an accessible image when given a title', () => {
    const w = mount(Icon, { props: { name: 'trash', title: 'Supprimer' } })
    const svg = w.find('svg')
    expect(svg.attributes('role')).toBe('img')
    expect(svg.attributes('aria-label')).toBe('Supprimer')
    expect(svg.attributes('aria-hidden')).toBeUndefined()
    expect(w.find('title').text()).toBe('Supprimer')
  })

  it('applies size and renders no shapes for an unknown name', () => {
    const w = mount(Icon, { props: { name: 'does-not-exist', size: 24 } })
    expect(w.find('svg').attributes('width')).toBe('24')
    expect(w.findAll('path').length).toBe(0)
    expect(w.findAll('circle').length).toBe(0)
  })
})
