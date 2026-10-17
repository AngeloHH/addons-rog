import { useEffect, useId, useRef, useState } from 'react';
import Icon from '../Icon/Icon.jsx';
import styles from './TagSelect.module.scss';

const normalize = (value) =>
  value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase();

/**
 * Render controlled multiple selection.
 * Include the group in option keys to distinguish identical labels.
 */
export default function TagSelect({
  label,
  placeholder,
  groups,
  value,
  onChange,
  disabled = false,
  emptyText,
  removeLabel,
  hint,
}) {
  const id = useId();
  const input = useRef(null);
  const list = useRef(null);
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const options = groups.flatMap((group) =>
    group.items.map((item) => ({ ...item, group: group.label })),
  );
  const selected = value.map(
    (key) =>
      options.find((option) => option.id === key) || { id: key, label: key.split(':').pop() },
  );
  const matches = options.filter(
    (option) =>
      !value.includes(option.id) &&
      normalize(`${option.label} ${option.group}`).includes(normalize(query.trim())),
  );
  const expanded = open && !disabled;
  const activeId = expanded && matches[active] ? `${id}-option-${active}` : undefined;

  useEffect(() => {
    if (activeId)
      list.current?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
  }, [activeId]);

  function choose(option) {
    if (disabled || !option || value.includes(option.id)) return;
    onChange([...value, option.id]);
    setQuery('');
    setActive(-1);
    input.current?.focus();
  }

  function keyDown(event) {
    if (event.nativeEvent.isComposing) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      setOpen(true);
      setActive(
        matches.length
          ? event.key === 'ArrowDown'
            ? (active + 1) % matches.length
            : active <= 0
              ? matches.length - 1
              : active - 1
          : -1,
      );
    } else if (event.key === 'Enter' && expanded) {
      event.preventDefault();
      choose(matches[active]);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      setOpen(false);
      setActive(-1);
    } else if (expanded && (event.key === 'Home' || event.key === 'End')) {
      event.preventDefault();
      setActive(event.key === 'Home' ? 0 : matches.length - 1);
    }
  }

  return (
    <div
      className={styles.root}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) {
          setOpen(false);
          setActive(-1);
        }
      }}
    >
      <label className={styles.label} htmlFor={id}>
        {label}
      </label>
      <div
        className={styles.control}
        data-disabled={disabled || undefined}
        onClick={() => input.current?.focus()}
      >
        {selected.map((option) => (
          <span className={styles.tag} key={option.id}>
            <span>{option.label}</span>
            <button
              type="button"
              disabled={disabled}
              aria-label={removeLabel(option.label)}
              onClick={(event) => {
                event.stopPropagation();
                onChange(value.filter((key) => key !== option.id));
                setActive(-1);
                input.current?.focus();
              }}
            >
              <Icon name="close" size={12} />
            </button>
          </span>
        ))}
        <input
          ref={input}
          id={id}
          className={styles.input}
          type="text"
          role="combobox"
          autoComplete="off"
          disabled={disabled}
          value={query}
          placeholder={placeholder}
          aria-autocomplete="list"
          aria-expanded={expanded}
          aria-controls={`${id}-list`}
          aria-activedescendant={activeId}
          aria-describedby={hint ? `${id}-hint` : undefined}
          onFocus={() => setOpen(true)}
          onChange={(event) => {
            setQuery(event.target.value);
            setOpen(true);
            setActive(-1);
          }}
          onKeyDown={keyDown}
        />
      </div>
      {hint && (
        <span className={styles.hint} id={`${id}-hint`}>
          {hint}
        </span>
      )}
      {expanded && (
        <div className={styles.dropdown}>
          <div
            ref={list}
            id={`${id}-list`}
            role="listbox"
            aria-label={label}
            aria-multiselectable="true"
          >
            {groups.map((group, groupIndex) => {
              const items = matches.filter((item) =>
                group.items.some((option) => option.id === item.id),
              );
              return (
                items.length > 0 && (
                  <div role="group" aria-labelledby={`${id}-group-${groupIndex}`} key={group.id}>
                    <div className={styles.groupLabel} id={`${id}-group-${groupIndex}`}>
                      {group.label}
                    </div>
                    {items.map((option) => {
                      const index = matches.indexOf(option);
                      return (
                        <div
                          id={`${id}-option-${index}`}
                          key={option.id}
                          role="option"
                          aria-selected="false"
                          className={styles.option}
                          data-active={index === active}
                          onMouseDown={(event) => event.preventDefault()}
                          onClick={() => choose(option)}
                        >
                          {option.label}
                        </div>
                      );
                    })}
                  </div>
                )
              );
            })}
          </div>
          {matches.length === 0 && (
            <div className={styles.empty} role="status">
              {emptyText}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
