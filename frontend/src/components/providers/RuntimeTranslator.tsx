'use client';

import { useEffect } from 'react';
import { runtimeFrench, translateDynamicFrench } from '@/i18n/runtime-translations';
import { apiFrench } from '@/i18n/translations';
import { useLanguageStore } from '@/store/language';

const TEXT_SKIP_PARENTS = new Set(['SCRIPT', 'STYLE', 'CODE', 'PRE', 'TEXTAREA']);
const ATTRIBUTES = ['placeholder', 'title', 'aria-label'] as const;
const originalTexts = new WeakMap<Text, string>();
const renderedTexts = new WeakMap<Text, string>();
const originalAttributes = new WeakMap<Element, Map<string, string>>();
const renderedAttributes = new WeakMap<Element, Map<string, string>>();

function normalized(value: string): string {
  return value.replace(/\s+/g, ' ').trim();
}

function frenchFor(value: string): string | null {
  const key = normalized(value);
  if (!key) return null;
  return runtimeFrench[key] ?? apiFrench[key] ?? translateDynamicFrench(key);
}

function translatedText(original: string, french: string): string {
  const leading = original.match(/^\s*/)?.[0] ?? '';
  const trailing = original.match(/\s*$/)?.[0] ?? '';
  return `${leading}${french}${trailing}`;
}

function translateTextNode(node: Text, language: 'fr' | 'en'): void {
  const parent = node.parentElement;
  if (!parent || TEXT_SKIP_PARENTS.has(parent.tagName)) return;

  if (language === 'fr') {
    const current = node.nodeValue ?? '';
    if (renderedTexts.get(node) === current) return;
    const french = frenchFor(current);
    if (!french) return;
    const rendered = translatedText(current, french);
    originalTexts.set(node, current);
    renderedTexts.set(node, rendered);
    node.nodeValue = rendered;
    return;
  }

  const original = originalTexts.get(node);
  if (original !== undefined) {
    node.nodeValue = original;
    originalTexts.delete(node);
    renderedTexts.delete(node);
  }
}

function translateAttributes(element: Element, language: 'fr' | 'en'): void {
  const originals = originalAttributes.get(element) ?? new Map<string, string>();
  const rendered = renderedAttributes.get(element) ?? new Map<string, string>();
  for (const attribute of ATTRIBUTES) {
    if (language === 'fr') {
      const current = element.getAttribute(attribute);
      if (!current) continue;
      if (rendered.get(attribute) === current) continue;
      const french = frenchFor(current);
      if (!french) continue;
      originals.set(attribute, current);
      rendered.set(attribute, french);
      element.setAttribute(attribute, french);
    } else {
      const original = originals.get(attribute);
      if (original !== undefined) {
        element.setAttribute(attribute, original);
        originals.delete(attribute);
        rendered.delete(attribute);
      }
    }
  }
  if (originals.size > 0) originalAttributes.set(element, originals);
  else originalAttributes.delete(element);
  if (rendered.size > 0) renderedAttributes.set(element, rendered);
  else renderedAttributes.delete(element);
}

function translateTree(root: Node, language: 'fr' | 'en'): void {
  if (root.nodeType === Node.TEXT_NODE) {
    translateTextNode(root as Text, language);
    return;
  }

  if (root instanceof Element) translateAttributes(root, language);
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
  let current: Node | null;
  while ((current = walker.nextNode())) {
    if (current.nodeType === Node.TEXT_NODE) translateTextNode(current as Text, language);
    else translateAttributes(current as Element, language);
  }
}

/**
 * Translates legacy hardcoded UI at the DOM boundary. The observer covers
 * route changes, modals, async tables, toasts, and validation messages.
 */
export function RuntimeTranslator() {
  const language = useLanguageStore((state) => state.language);

  useEffect(() => {
    document.documentElement.lang = language;
    translateTree(document.documentElement, language);

    if (language === 'en') return undefined;

    const nativeAlert = window.alert.bind(window);
    const nativeConfirm = window.confirm.bind(window);
    const nativePrompt = window.prompt.bind(window);
    window.alert = (message?: unknown) => nativeAlert(typeof message === 'string' ? frenchFor(message) ?? message : message);
    window.confirm = (message?: string) => nativeConfirm(message ? frenchFor(message) ?? message : message);
    window.prompt = (message?: string, defaultValue?: string) => nativePrompt(message ? frenchFor(message) ?? message : message, defaultValue);

    const observer = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        if (mutation.type === 'characterData') translateTree(mutation.target, language);
        for (const node of mutation.addedNodes) translateTree(node, language);
      }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true, characterData: true });

    return () => {
      observer.disconnect();
      window.alert = nativeAlert;
      window.confirm = nativeConfirm;
      window.prompt = nativePrompt;
    };
  }, [language]);

  return null;
}
