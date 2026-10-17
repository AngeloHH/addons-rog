/**
 * Addons ROG - Checker
 *
 * Render a controlled checklist and its supplied verdict.
 * The caller stores answers by question ID and decides the final outcome.
 *
 * answers  Answer values indexed by question ID.
 * options  { value, label, tone }; selected 'accept' is gold and 'reject' is red.
 * verdict  { title, text, tone, action }; tone is neutral, warning, danger, or success.
 */

import { Link } from 'react-router-dom';
import styles from './Checker.module.scss';

export default function Checker({
  answers = {},
  kicker,
  note,
  options = [],
  questions = [],
  title,
  verdict,
  onAnswer,
}) {
  return (
    <div className={styles.checker}>
      <div className={styles.header}>
        {kicker && <span className={styles.kicker}>{kicker}</span>}
        {title && <span className={styles.title}>{title}</span>}
        {note && <span className={styles.note}>{note}</span>}
      </div>

      <div className={styles.questions}>
        {questions.map((question) => (
          <div className={styles.question} key={question.id}>
            <span className={styles.questionText}>{question.text}</span>

            <span className={styles.options}>
              {options.map((option) => {
                const selected = answers[question.id] === option.value;

                return (
                  <button
                    aria-pressed={selected}
                    className={styles.option}
                    data-selected={selected ? 'true' : 'false'}
                    data-tone={option.tone}
                    key={option.label}
                    type="button"
                    onClick={() => onAnswer?.(question.id, option.value)}
                  >
                    {option.label}
                  </button>
                );
              })}
            </span>
          </div>
        ))}
      </div>

      {verdict && (
        <div className={styles.verdict} data-tone={verdict.tone}>
          <span className={styles.verdictTitle}>
            <span className={styles.diamond} />
            {verdict.title}
          </span>
          <span className={styles.verdictText}>{verdict.text}</span>

          {verdict.action && (
            <Link className={styles.verdictAction} to={verdict.action.to}>
              {verdict.action.label}
            </Link>
          )}
        </div>
      )}
    </div>
  );
}
