((Drupal, once) => {
  Drupal.behaviors.autofillFromAnotherField = {
    attach: (context, settings) => {
      const targetFieldWasManipulated = [];
      Object.keys(settings.autofill.field_mapping || {}).forEach(
        (targetField) => {
          const sourceField = settings.autofill.field_mapping[targetField];

          // Only process if source field and target field are present.
          const [sourceFieldElement] = once(
            `autofill_${sourceField}_${targetField}`,
            context.querySelector(`[name="${sourceField}[0][value]"]`),
          );
          if (!sourceFieldElement) {
            return;
          }

          const targetFieldElement = context.querySelector(
            `[name="${targetField}[0][value]"]`,
          );
          if (!targetFieldElement) {
            return;
          }
          targetFieldWasManipulated[targetField] = false;

          // Automatically fill target field with value of the source
          // field, when it's empty or values are identical.
          if (
            !sourceFieldElement.value ||
            sourceFieldElement.value === targetFieldElement.value
          ) {
            sourceFieldElement.addEventListener('input', () => {
              // Autofill the target field only when it was not manipulated
              // before.
              if (!targetFieldWasManipulated[targetField]) {
                targetFieldElement.value = sourceFieldElement.value;
                // Trigger input event, to fire additional events, like
                // length indicator.
                targetFieldElement.dispatchEvent(new Event('input'));
              }
            });
          } else {
            targetFieldWasManipulated[targetField] = true;
          }

          // Store, when target field was manipulated manually. Then we
          // should not process the autofill again.
          targetFieldElement.addEventListener('keypress', () => {
            targetFieldWasManipulated[targetField] = true;
          });
        },
      );
    },
  };
})(Drupal, once);
