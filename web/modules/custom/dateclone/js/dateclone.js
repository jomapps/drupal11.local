/**
 * @file
 * DateClone JavaScript functionality for event duplication.
 */

(function ($, Drupal, drupalSettings, once) {

  'use strict';

  /**
   * DateClone behavior for event forms.
   */
  Drupal.behaviors.dateclone = {
    attach: function (context, settings) {
      // Use once() to ensure this only runs once per element
      const elements = once('dateclone', '#js-dateclone', context);
      elements.forEach(function(element) {
        if (settings.dateclone) {
          // Initialize after a brief delay to ensure DOM is ready
          setTimeout(function() {
            Drupal.dateclone.init(settings.dateclone);
          }, 500);
        }
      });
    }
  };

  /**
   * DateClone functionality object.
   */
  Drupal.dateclone = {
    settings: null,
    container: null,
    $datesContainer: null,
    $doneContainer: null,
    dates: [],
    tabindex: [],

    /**
     * Initialize the DateClone interface.
     */
    init: function(settings) {
      this.settings = settings;
      this.container = $(settings.containerId);
      
      if (!this.container.length) {
        return;
      }

      this.clearContainer();
      this.addWeekdaysButton();
      this.addDateButton();
      this.addClearDatesContainerButton();
      this.addSortButton();
      
      if (settings.nid !== null) {
        this.pushButton();
      }

      this.addDatesContainer();
      this.addDoneContainer();
      this.addTitleCleanButton();
      
      // Set tab index after a delay
      setTimeout(() => {
        this.changeTabindex();
      }, 500);
    },

    /**
     * Clean up the title (capitalize words).
     */
    cleanTitle: function() {
      const title = $(this.settings.titleFieldId).val();
      const newTitle = title.toLowerCase().replace(/\b[a-z]/g, function(letter) {
        return letter.toUpperCase();
      });
      $(this.settings.titleFieldId).val(newTitle.trim());
    },

    /**
     * Remove all dates with confirmation.
     */
    removeDates: function() {
      if (!confirm('Werte jetzt entfernen?')) {
        return;
      }
      this.dates = [];
      this.render();
    },

    /**
     * Add a new date/time entry.
     */
    pushDate: function(date, time) {
      this.dates.push({
        uid: null,
        date: date,
        time: time
      });

      this.updateDateInfos(this.dates.length - 1);

      // Regenerate UIDs
      for (let i = 0; i < this.dates.length; i++) {
        this.dates[i].uid = 'dt-' + (i + 1);
      }

      this.render();
    },

    /**
     * Update tab order for better accessibility.
     */
    changeTabindex: function() {
      const tabs = this.settings.tabindex;
      for (let i = 0; i < tabs.length; i++) {
        $(tabs[i]).attr('tabindex', i + 1);
      }

      // Focus on first element
      if (tabs.length > 0) {
        $(tabs[0]).focus();
      }
    },

    /**
     * Sort dates chronologically.
     */
    reOrder: function() {
      this.dates.sort((a, b) => (a.seconds > b.seconds) ? 1 : -1);
    },

    /**
     * Remove date at specific index.
     */
    removeDate: function(index) {
      this.dates.splice(index, 1);
      this.render();
    },

    /**
     * Update date value and recalculate info.
     */
    updateDate: function(index, value) {
      this.dates[index].date = value;
      this.updateDateInfos(index);
      this.render();
    },

    /**
     * Update time value and recalculate info.
     */
    updateTime: function(index, value) {
      this.dates[index].time = value;
      this.updateDateInfos(index);
      this.render();
    },

    /**
     * Update calculated date information (seconds, label).
     */
    updateDateInfos: function(index) {
      const value = this.dates[index];
      if (!value || !value.date || !value.time) {
        return;
      }

      const d = new Date(Date.parse(value.date + ' ' + value.time));
      this.dates[index].seconds = d.getTime();
      this.dates[index].label = d.toLocaleDateString('de-DE', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    },

    /**
     * Re-render all date input fields.
     */
    render: function() {
      this.clearDatesContainer();
      for (let i = 0; i < this.dates.length; i++) {
        this.createDateInputField(this.dates[i], i);
      }
    },

    /**
     * Add dates for a specific weekday in the current month.
     */
    pushWeekday: function(dayOfWeek) {
      if (!this.checkDefaultTime()) {
        return;
      }

      this.checkDefaultDate(dayOfWeek);
      const d = this.getLastWeekdayDate(dayOfWeek);
      
      d.setDate(d.getDate() + 7);
      const month = d.getMonth();

      while (d.getMonth() === month) {
        const date = d.toISOString().slice(0, 10);
        const time = $(this.settings.timeFieldId).val();
        this.pushDate(date, time);
        d.setDate(d.getDate() + 7);
      }
    },

    /**
     * Check if default time is set.
     */
    checkDefaultTime: function() {
      const time = $(this.settings.timeFieldId).val();
      if (!time) {
        alert('Es wird eine Uhrzeit benötigt');
        return false;
      }
      return true;
    },

    /**
     * Check and set default date if needed.
     */
    checkDefaultDate: function(dayOfWeek) {
      const date = $(this.settings.dateFieldId).val();
      if (!date) {
        const month = new Date().getMonth() + 1;
        const d = this.getFirstDateOfMonth(dayOfWeek, month, 1);
        $(this.settings.dateFieldId).val(d.toISOString().slice(0, 10));
      }
    },

    /**
     * Get first occurrence of weekday in month.
     */
    getFirstDateOfMonth: function(dayOfWeek, month, day, year) {
      const d = new Date();
      d.setHours(6, 0, 0);

      if (day !== null) d.setDate(day);
      if (month !== null) d.setMonth(month);
      if (year !== null) d.setFullYear(year);

      while (d.getDay() !== parseInt(dayOfWeek)) {
        d.setDate(d.getDate() + 1);
      }

      return d;
    },

    /**
     * Get default date from form fields.
     */
    getDefaultDate: function() {
      const dateField = $(this.settings.dateFieldId).val();
      const timeField = $(this.settings.timeFieldId).val();
      return new Date(Date.parse(dateField + ' ' + timeField));
    },

    /**
     * Get last occurrence of weekday.
     */
    getLastWeekdayDate: function(dayOfWeek) {
      // If no dates exist, check default date
      if (this.dates.length === 0) {
        const d = this.getDefaultDate();
        if (d.getDay() === parseInt(dayOfWeek)) {
          return d;
        }
      }

      // Check existing dates for this weekday
      let lastDate = null;
      for (let i = 0; i < this.dates.length; i++) {
        const d = new Date(this.dates[i].date);
        if (d.getDay() === parseInt(dayOfWeek) && 
            (lastDate === null || lastDate.getTime() < d.getTime())) {
          lastDate = d;
        }
      }

      if (lastDate !== null) {
        return lastDate;
      }

      // Generate new date for this weekday
      const defaultDate = this.getDefaultDate();
      const d = this.getFirstDateOfMonth(dayOfWeek, defaultDate.getMonth(), 
                                        defaultDate.getDate(), defaultDate.getFullYear());
      d.setDate(d.getDate() - 7);
      return d;
    },

    // UI Creation Methods

    /**
     * Clear main container.
     */
    clearContainer: function() {
      this.container.html('');
    },

    /**
     * Add dates container.
     */
    addDatesContainer: function() {
      this.$datesContainer = $('<div id="js-dateclone-dates"></div>');
      this.container.append('<hr style="margin-top: 4px; margin-bottom: 4px;" />')
                   .append(this.$datesContainer);
    },

    /**
     * Add done container for completed operations.
     */
    addDoneContainer: function() {
      this.$doneContainer = $('<div id="js-dateclone-done"></div>');
      this.container.append('<hr style="margin-top: 4px; margin-bottom: 4px;" />')
                   .append(this.$doneContainer);
    },

    /**
     * Add weekday buttons.
     */
    addWeekdaysButton: function() {
      const weekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
      const self = this;

      for (let i = 0; i < weekdays.length; i++) {
        let day = i + 1;
        if (day === 7) day = 0;

        const $btn = $('<button type="button" id="weekday-' + day + 
                      '" value="' + weekdays[i] + '" data-day="' + day + 
                      '" class="button" style="margin-left: 0; margin-right: 4px">' + 
                      weekdays[i] + '</button>');
        
        this.container.append($btn);

        $btn.on('click', function() {
          const day = $(this).attr('data-day');
          self.pushWeekday(day);
        });
      }
      this.container.append('<hr style="margin-top: 4px; margin-bottom: 4px;" />');
    },

    /**
     * Add date button.
     */
    addDateButton: function() {
      const self = this;
      const $btn = $('<input type="button" id="add-date" value="Datum hinzufügen" ' +
                    'class="button" style="margin-left: 0; margin-right: 4px" />');
      this.container.append($btn);
      
      $btn.on('click', function() {
        const time = $(self.settings.timeFieldId).val();
        self.pushDate(null, time);
      });
    },

    /**
     * Add clear all button.
     */
    addClearDatesContainerButton: function() {
      const self = this;
      const $btn = $('<input type="button" value="Alle entfernen" ' +
                    'class="button" style="margin-left: 0; margin-right: 4px" />');
      this.container.append($btn);
      $btn.on('click', function() { self.removeDates(); });
    },

    /**
     * Add sort button.
     */
    addSortButton: function() {
      const self = this;
      const $btn = $('<input type="button" value="Sortieren" ' +
                    'class="button" style="margin-left: 0; margin-right: 4px" />');
      this.container.append($btn);
      
      $btn.on('click', function() {
        self.reOrder();
        self.render();
      });
    },

    /**
     * Add title clean button.
     */
    addTitleCleanButton: function() {
      const self = this;
      const p = $(this.settings.titleFieldId).parent();
      const $btn = $('<input type="button" id="remove-versalien" ' +
                    'value="Versalien entfernen" class="button" ' +
                    'style="margin-left: 0; margin-top: 4px; width: 200px" />');
      p.append($btn);
      $btn.on('click', function() { self.cleanTitle(); });
    },

    /**
     * Clear dates container.
     */
    clearDatesContainer: function() {
      if (this.$datesContainer) {
        this.$datesContainer.html('');
      }
    },

    /**
     * Add push/duplicate button for existing events.
     */
    pushButton: function() {
      const self = this;
      const $btn = $('<input type="button" value="Inhalte duplizieren" ' +
                    'class="button" style="margin-left: 0; margin-right: 4px" />');
      this.container.append($btn);
      $btn.on('click', function() { self.pushData(); });
    },

    /**
     * Create date input field row.
     */
    createDateInputField: function(value, index) {
      const self = this;
      const container = $('<div class="form-item__field-wrapper container-inline" ' +
                         'id="' + value.uid + '" style="margin-bottom: 4px"></div>');
      
      const removeBtn = $('<input type="button" value="#' + (index + 1) + ' | ' + 
                         value.label + ' Entfernen" class="button" />');
      
      const inputDate = $('<input type="date" name="dateclone[' + index + '][date]" ' +
                         'min="1900-01-01" max="2050-12-31" value="' + value.date + 
                         '" size="12" class="form-date" required="required" ' +
                         'style="margin-right: 4px">');
      
      const inputTime = $('<input type="time" name="dateclone[' + index + '][time]" ' +
                         'step="1" value="' + value.time + '" size="12" ' +
                         'class="form-time" required="required">');
      
      const inputUid = $('<input type="hidden" name="dateclone[' + index + '][uid]" ' +
                        'value="' + value.uid + '">');

      // Event handlers
      removeBtn.on('click', function() { self.removeDate(index); });
      inputDate.on('blur', function() { self.updateDate(index, $(this).val()); });
      inputTime.on('blur', function() { self.updateTime(index, $(this).val()); });

      container.append(inputDate)
               .append(inputTime)
               .append(removeBtn)
               .append(inputUid);

      this.$datesContainer.append(container);
    },

    /**
     * Push data via AJAX for existing events.
     */
    pushData: function() {
      if (!confirm('Inhalt jetzt duplizieren?')) {
        return;
      }

      const data = this.getValues();
      if (data.length === 0) {
        return;
      }

      $.post(this.settings.url, {
        nid: this.settings.nid,
        data: data
      })
      .done((response) => {
        if (response && response.length !== 0) {
          this.feedback(response);
        }
      })
      .fail(() => {
        alert('Fehler beim Duplizieren der Events.');
      });
    },

    /**
     * Display feedback for successful duplication.
     */
    feedback: function(values) {
      for (let i = 0; i < values.length; i++) {
        const value = values[i];
        const item = $('#' + value.uid);
        item.detach().appendTo('#js-dateclone-done');
        item.html('<a href="/node/' + value.nid + '" target="_blank">' +
                 'Event: ' + value.nid + ' wurde erstellt</a>');
      }
    },

    /**
     * Get current form values for AJAX submission.
     */
    getValues: function() {
      const values = [];
      const dates = this.$datesContainer.children();

      for (let i = 0; i < dates.length; i++) {
        const uid = $(dates[i]).attr('id');
        const date = $(dates[i]).find(':input.form-date').val();
        const time = $(dates[i]).find(':input.form-time').val();

        values.push({
          uid: uid,
          date: date,
          time: time
        });
      }

      return values;
    }
  };

})(jQuery, Drupal, drupalSettings, once);
