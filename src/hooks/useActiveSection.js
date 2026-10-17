import { useEffect, useState } from 'react';

// Use the fixed header as the reading line; the last heading above it is active.
const headerOffset = 97;

/**
 * Track the section currently being read from heading positions.
 * Intersection visibility alone misses the active section during large scroll jumps.
 * Schedule measurements with requestAnimationFrame to run at most once per frame.
 */
export function useActiveSection(ids) {
  const [active, setActive] = useState(null);
  const key = ids.join('|');

  useEffect(() => {
    const elements = key
      .split('|')
      .map((id) => document.getElementById(id))
      .filter(Boolean);

    if (elements.length === 0) return undefined;

    let frame = null;

    function measure() {
      frame = null;

      let current = elements[0].id;

      for (const element of elements) {
        if (element.getBoundingClientRect().top <= headerOffset) current = element.id;
      }

      setActive(current);
    }

    function schedule() {
      if (frame === null) frame = window.requestAnimationFrame(measure);
    }

    measure();
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);

    return () => {
      if (frame !== null) window.cancelAnimationFrame(frame);

      window.removeEventListener('scroll', schedule);
      window.removeEventListener('resize', schedule);
    };
  }, [key]);

  return active;
}
