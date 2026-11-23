<?php
/**
 * Post-event report survey.
 *
 * The question list lives in one option and is edited under Wydarzenia → Ankieta raportu.
 * The organiser panel report form (events-reports.php) renders it, and the answers are stored
 * on the event together with a copy of the questions as they were shown, so later edits of the
 * survey never change what an organiser sent.
 */

define( 'MARATON_SURVEY_OPTION', 'maraton_report_survey' );
define( 'MARATON_SURVEY_META', 'raport_ankieta' );
define( 'MARATON_SURVEY_OTHER', '__other__' );
define( 'MARATON_SURVEY_MAX_NUMBER', 1000000 );

// 1. SURVEY DEFINITION

function maraton_survey_types() {
    return array(
        'heading'  => __( 'Nagłówek sekcji', 'amnestympl' ),
        'text'     => __( 'Krótka odpowiedź', 'amnestympl' ),
        'textarea' => __( 'Długa odpowiedź', 'amnestympl' ),
        'number'   => __( 'Liczba', 'amnestympl' ),
        'radio'    => __( 'Jeden wybór', 'amnestympl' ),
        'checkbox' => __( 'Wielokrotny wybór', 'amnestympl' ),
    );
}

function maraton_survey_is_choice( $question ) {
    return in_array( $question['type'], array( 'radio', 'checkbox' ), true );
}

function maraton_survey_default() {
    $rating = array( 'Bardzo dobrze', 'Dobrze', 'Przeciętnie', 'Źle', 'Bardzo źle' );
    $yes_no = array( 'Tak', 'Nie' );

    $q = static function ( $id, $type, $label, $extra = array() ) {
        return array_merge(
            array(
                'id'        => $id,
                'type'      => $type,
                'label'     => $label,
                'required'  => false,
                'options'   => array(),
                'other'     => '',
                'condition' => null,
            ),
            $extra
        );
    };

    $questions = array(
        $q( 'q_section_report', 'heading', 'Raport' ),
        $q( 'q_letters', 'letters', 'Ile listów udało się napisać?', array( 'petitions' => array() ) ),
        $q( 'q_participants', 'number', 'Ile osób wzięło udział w wydarzeniu?', array( 'required' => true ) ),
        $q( 'q_education', 'radio', 'Czy Maratonowi towarzyszyły zajęcia edukacyjne?', array( 'required' => true, 'options' => $yes_no ) ),
        $q( 'q_education_count', 'number', 'Ile osób wzięło udział w zajęciach edukacyjnych?', array( 'condition' => array( 'question' => 'q_education', 'value' => 'Tak' ) ) ),
        $q( 'q_activities', 'textarea', 'Czy pisaniu listów towarzyszyły inne aktywności, dodatkowe atrakcje i wydarzenia?' ),

        $q( 'q_section_survey', 'heading', 'Ankieta' ),
        $q( 'q_school', 'radio', 'Do jakiej szkoły uczęszczasz/w jakiej szkole nauczasz?', array(
            'required' => true,
            'options'  => array( 'Przedszkole', 'Szkoła podstawowa', 'Szkoła ponadpodstawowa (liceum, technikum, szkoła branżowa)', 'Uczelnia wyższa', 'Nie jestem uczniem_uczennicą/nauczycielem_nauczycielką' ),
        ) ),
        $q( 'q_edition', 'number', 'Który raz organizowałeś_aś Maraton?', array( 'required' => true ) ),
        $q( 'q_first_here', 'radio', 'Czy to pierwszy Maraton organizowany w Twojej szkole/miejscu pracy?', array( 'required' => true, 'options' => array( 'Tak', 'Nie', 'Nie wiem' ) ) ),
        $q( 'q_source', 'checkbox', 'Skąd dowiedziałeś_aś się o Maratonie Pisania Listów?', array(
            'required' => true,
            'options'  => array( 'uczestniczyłem_am w poprzednich edycjach Maratonu', 'brałem_am udział w innych działaniach Amnesty Polska', 'usłyszałem_am o nim na lekcji w szkole', 'z newslettera Amnesty', 'od innej organizacji', 'z mediów (artykuł, audycja radiowa)', 'z mediów społecznościowych (Internet)', 'od rodziny/znajomych', 'z innego źródła' ),
        ) ),
        $q( 'q_source_web', 'checkbox', 'Jeśli z Internetu, to z jakiego źródła?', array(
            'options'   => array( 'strona Amnesty Polska', 'Facebook', 'Instagram', 'X (Twitter)', 'LinkedIn', 'inne źródła internetowe' ),
            'condition' => array( 'question' => 'q_source', 'value' => 'z mediów społecznościowych (Internet)' ),
        ) ),
        $q( 'q_rate_heroes', 'radio', 'Jak oceniasz dobór i przygotowanie opisów bohaterów_bohaterek Maratonu {rok}?', array( 'required' => true, 'options' => $rating ) ),
        $q( 'q_rate_office', 'radio', 'Jak oceniasz wsparcie ze strony biura Stowarzyszenia Amnesty International Polska?', array( 'required' => true, 'options' => $rating ) ),
        $q( 'q_rate_website', 'radio', 'Jak oceniasz stronę internetową i profil miejsca maratonowego?', array( 'required' => true, 'options' => $rating ) ),
        $q( 'q_rate_materials', 'radio', 'Jak oceniasz materiały dla Organizatorów_ek?', array( 'required' => true, 'options' => $rating ) ),
        $q( 'q_rate_why', 'textarea', 'Jeśli w którymś z powyższych pytań zaznaczyłeś_aś odpowiedź „Przeciętnie”, „Źle” lub „Bardzo źle”, napisz proszę, dlaczego:' ),
        $q( 'q_promotion', 'checkbox', 'Jakich kanałów/narzędzi używałeś_aś do promocji Maratonu?', array(
            'required' => true,
            'options'  => array( 'Media społecznościowe', 'Plakaty, ulotki', 'Lokalna prasa', 'Radio', 'Lokalna telewizja', 'System Librus' ),
            'other'    => 'Inne (jakie?)',
        ) ),
        $q( 'q_helpful_materials', 'checkbox', 'Jakie materiały były dla Ciebie pomocne? Które z nich wykorzystałeś_aś?', array(
            'required' => true,
            'options'  => array( 'Plakaty ogólne', 'Plakaty z bohaterami_bohaterkami', 'Karty bohaterów_bohaterek', 'Wzory listów', 'Materiały na media społecznościowe', 'Materiały edukacyjne (scenariusze lekcji)', 'Podręcznik Organizatora_Organizatorki', 'Prezentacje', 'Ulotki', 'Materiały wideo' ),
        ) ),
        $q( 'q_funding', 'checkbox', 'W jaki sposób zebrałeś_aś fundusze na znaczki?', array(
            'required' => true,
            'options'  => array( 'Poprzez Radę Rodziców', 'Zbiórka pieniędzy do puszki', 'Pomógł lokalny sponsor/współpraca z biznesem', 'Zbiórka na Facebooku', 'Środki własne instytucji/organizacji/firmy', 'Środki prywatne' ),
            'other'    => 'Inny sposób (jaki?)',
        ) ),
        $q( 'q_engagement', 'checkbox', 'Jaką formę zaangażowania wybrałeś_aś?', array(
            'required' => true,
            'options'  => array( 'Listy tradycyjne', 'Listy elektroniczne (e-maile)', 'Podpisywanie petycji', 'Udostępnianie informacji o Maratonie w mediach społecznościowych' ),
            'other'    => 'Inna (jaka?)',
        ) ),
        $q( 'q_next_edition', 'radio', 'Czy podejmiesz się organizacji Maratonu w kolejnych edycjach?', array( 'required' => true, 'options' => array( 'Tak', 'Nie', 'Nie wiem' ) ) ),
        $q( 'q_next_edition_why', 'textarea', 'Napisz proszę, dlaczego nie:', array( 'condition' => array( 'question' => 'q_next_edition', 'value' => 'Nie' ) ) ),
        $q( 'q_comments', 'textarea', 'Czy masz dla nas jakieś uwagi/komentarze?' ),
        $q( 'q_discord', 'radio', 'Czy dołączyłeś_aś do czatu o Maratonie na platformie Discord?', array( 'required' => true, 'options' => $yes_no ) ),
        $q( 'q_discord_opinion', 'textarea', 'Jeśli tak – co sądzisz o czacie? Jeśli nie – dlaczego?', array( 'required' => true ) ),
    );

    return array(
        'version'   => 0,
        'questions' => $questions,
        'archived'  => array(),
    );
}

function maraton_survey_get() {
    $saved = get_option( MARATON_SURVEY_OPTION );

    if ( is_array( $saved ) && ! empty( $saved['questions'] ) && is_array( $saved['questions'] ) ) {
        return wp_parse_args( $saved, array( 'version' => 0, 'archived' => array() ) );
    }

    return maraton_survey_default();
}

function maraton_survey_find( $questions, $id ) {
    foreach ( $questions as $question ) {
        if ( $question['id'] === $id ) {
            return $question;
        }
    }
    return null;
}

function maraton_survey_new_id( $taken ) {
    do {
        $id = 'q_' . strtolower( wp_generate_password( 8, false ) );
    } while ( isset( $taken[ $id ] ) );

    return $id;
}

/**
 * Cleans the question list sent by the builder.
 * Returns array( $survey, $notices ); questions missing from the new list go to 'archived'
 * so old answers still get a column label in the CSV export.
 */
function maraton_survey_sanitize( $raw, $previous ) {
    $types   = maraton_survey_types();
    $notices = array();
    $clean   = array();
    $taken   = array();
    $letters = false;

    foreach ( (array) $raw as $item ) {
        if ( ! is_array( $item ) ) {
            continue;
        }

        $type = isset( $item['type'] ) ? (string) $item['type'] : '';
        if ( 'letters' === $type ) {
            if ( $letters ) {
                continue;
            }
            $letters = true;
        } elseif ( ! isset( $types[ $type ] ) ) {
            continue;
        }

        $label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
        if ( '' === $label ) {
            if ( 'letters' !== $type ) {
                continue; // An empty card added and never filled in
            }
            $label = __( 'Ile listów udało się napisać?', 'amnestympl' );
        }

        $id = isset( $item['id'] ) ? (string) $item['id'] : '';
        if ( ! preg_match( '/^q_[a-z0-9_]{1,40}$/', $id ) || isset( $taken[ $id ] ) ) {
            $id = maraton_survey_new_id( $taken );
        }
        $taken[ $id ] = true;

        $question = array(
            'id'        => $id,
            'type'      => $type,
            'label'     => $label,
            'required'  => ! in_array( $type, array( 'heading', 'letters' ), true ) && ! empty( $item['required'] ),
            'options'   => array(),
            'other'     => '',
            'condition' => null,
        );

        if ( maraton_survey_is_choice( $question ) ) {
            foreach ( (array) ( $item['options'] ?? array() ) as $option ) {
                $option = sanitize_text_field( (string) $option );
                if ( '' !== $option && ! in_array( $option, $question['options'], true ) ) {
                    $question['options'][] = $option;
                }
            }
            $question['other'] = sanitize_text_field( (string) ( $item['other'] ?? '' ) );

            if ( ! $question['options'] && '' === $question['other'] ) {
                /* translators: %s: question text */
                $notices[] = sprintf( __( 'Pytanie „%s” nie ma odpowiedzi do wyboru, więc nie pojawi się w formularzu.', 'amnestympl' ), $label );
            }
        }

        if ( 'letters' === $type ) {
            $rows = array();
            foreach ( (array) ( $item['petitions'] ?? array() ) as $row ) {
                $petition_id = absint( is_array( $row ) ? ( $row['id'] ?? 0 ) : $row );
                if ( $petition_id && ! isset( $rows[ $petition_id ] ) && 'amnesty_petition' === get_post_type( $petition_id ) ) {
                    $rows[ $petition_id ] = array(
                        'id'    => $petition_id,
                        'label' => is_array( $row ) ? sanitize_text_field( (string) ( $row['label'] ?? '' ) ) : '',
                    );
                }
            }
            $question['petitions'] = array_values( $rows );
        }

        if ( 'heading' !== $type && ! empty( $item['condition']['question'] ) ) {
            $target = maraton_survey_find( $clean, (string) $item['condition']['question'] );
            $value  = sanitize_text_field( (string) ( $item['condition']['value'] ?? '' ) );

            if ( $target && maraton_survey_is_choice( $target ) && in_array( $value, $target['options'], true ) ) {
                $question['condition'] = array( 'question' => $target['id'], 'value' => $value );
            } else {
                /* translators: %s: question text */
                $notices[] = sprintf( __( 'Warunek pytania „%s” wskazywał pytanie lub odpowiedź, których już nie ma wyżej w ankiecie, więc pytanie będzie widoczne zawsze.', 'amnestympl' ), $label );
            }
        }

        $clean[] = $question;
    }

    // The letters table is the core of the report and cannot be removed from the builder
    if ( ! $letters ) {
        $old_letters = null;
        foreach ( $previous['questions'] as $old ) {
            if ( 'letters' === $old['type'] ) {
                $old_letters = $old;
            }
        }
        if ( ! $old_letters ) {
            $old_letters = maraton_survey_find( maraton_survey_default()['questions'], 'q_letters' );
        }
        if ( isset( $taken[ $old_letters['id'] ] ) ) {
            $old_letters['id'] = maraton_survey_new_id( $taken );
        }
        $clean[] = $old_letters;
    }

    $archived = (array) ( $previous['archived'] ?? array() );
    foreach ( $previous['questions'] as $old ) {
        if ( 'heading' !== $old['type'] && ! isset( $taken[ $old['id'] ] ) ) {
            $archived[ $old['id'] ] = array( 'label' => $old['label'], 'type' => $old['type'] );
        }
    }
    $archived = array_diff_key( $archived, $taken );

    return array(
        array(
            'version'   => (int) ( $previous['version'] ?? 0 ) + 1,
            'questions' => $clean,
            'archived'  => $archived,
        ),
        $notices,
    );
}

function maraton_survey_event_year( $event_id ) {
    $date = function_exists( 'maraton_get_event_datetime' ) ? maraton_get_event_datetime( $event_id, 'end' ) : '';
    if ( empty( $date ) && function_exists( 'maraton_get_event_datetime' ) ) {
        $date = maraton_get_event_datetime( $event_id, 'start' );
    }
    $timestamp = $date ? strtotime( $date ) : false;

    return $timestamp ? gmdate( 'Y', $timestamp ) : wp_date( 'Y' );
}

function maraton_survey_label( $question, $year ) {
    return str_replace( '{rok}', $year, $question['label'] );
}

/**
 * Rows of the letters table: the petitions picked in the builder, or every published petition
 * when none are picked (so a new hero shows up without touching the survey).
 */
function maraton_survey_letter_rows( $question ) {
    $picked = ! empty( $question['petitions'] ) ? $question['petitions'] : array();

    if ( ! $picked ) {
        $ids = get_posts( array(
            'post_type'      => 'amnesty_petition',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
            'fields'         => 'ids',
        ) );
        $picked = array_map( static function ( $id ) {
            return array( 'id' => $id, 'label' => '' );
        }, $ids );
    }

    $rows = array();
    foreach ( $picked as $row ) {
        $petition = get_post( $row['id'] );
        if ( ! $petition || 'amnesty_petition' !== $petition->post_type || 'trash' === $petition->post_status ) {
            continue;
        }
        $rows[] = array(
            'id'    => $petition->ID,
            'label' => '' !== $row['label'] ? $row['label'] : maraton_survey_petition_label( $petition ),
        );
    }

    return $rows;
}

function maraton_survey_petition_label( $petition ) {
    // Titles saved in the editor can contain entities (&amp;); the label is escaped on output
    $title   = trim( html_entity_decode( $petition->post_title, ENT_QUOTES, 'UTF-8' ) );
    $country = get_post_meta( $petition->ID, '_amnesty_petition_country', true );
    return $country ? $title . ' (' . $country . ')' : $title;
}

// 2. ORGANISER PANEL: FORM FIELDS

function maraton_survey_render_fields( $event_id ) {
    $survey = maraton_survey_get();
    $year   = maraton_survey_event_year( $event_id );

    ob_start();
    echo '<div class="report-survey">';

    foreach ( $survey['questions'] as $question ) {
        $label = maraton_survey_label( $question, $year );
        $name  = 'survey[' . $question['id'] . ']';

        if ( 'heading' === $question['type'] ) {
            echo '<h3 class="form-section-title">' . esc_html( $label ) . '</h3>';
            continue;
        }
        if ( maraton_survey_is_choice( $question ) && ! $question['options'] && '' === $question['other'] ) {
            continue;
        }

        $attrs = 'class="form-section survey-question" data-question="' . esc_attr( $question['id'] ) . '" data-type="' . esc_attr( $question['type'] ) . '"';
        if ( $question['required'] ) {
            $attrs .= ' data-required="1"';
        }
        if ( $question['condition'] ) {
            // Hidden and disabled until the answer it depends on is picked (report-survey.js)
            $attrs .= ' data-condition-question="' . esc_attr( $question['condition']['question'] ) . '" data-condition-value="' . esc_attr( $question['condition']['value'] ) . '" hidden';
        }
        $disabled = $question['condition'] ? ' disabled' : '';
        $required = $question['required'] ? ' required' : '';
        $asterisk = $question['required'] ? ' <span class="required-asterisk">*</span>' : '';
        $field_id = 'survey-' . $question['id'];

        echo '<div ' . $attrs . '>';

        switch ( $question['type'] ) {
            case 'text':
            case 'number':
            case 'textarea':
                echo '<label class="form-label" for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . $asterisk . '</label>';
                if ( 'textarea' === $question['type'] ) {
                    echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" class="form-textarea" rows="4" maxlength="5000"' . $required . $disabled . '></textarea>';
                } elseif ( 'number' === $question['type'] ) {
                    echo '<input type="number" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" class="form-input survey-number" min="0" max="' . MARATON_SURVEY_MAX_NUMBER . '" step="1" inputmode="numeric"' . $required . $disabled . '>';
                } else {
                    echo '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" class="form-input" maxlength="500"' . $required . $disabled . '>';
                }
                break;

            case 'radio':
            case 'checkbox':
                $is_radio   = 'radio' === $question['type'];
                $input_name = $is_radio ? $name . '[selected]' : $name . '[selected][]';
                $choices    = $question['options'];
                if ( '' !== $question['other'] ) {
                    $choices[ MARATON_SURVEY_OTHER ] = $question['other'];
                }

                echo '<fieldset class="survey-choices"><legend class="form-label">' . esc_html( $label ) . $asterisk . '</legend>';
                foreach ( $choices as $key => $choice ) {
                    $value = MARATON_SURVEY_OTHER === $key ? MARATON_SURVEY_OTHER : $choice;
                    // Radio groups validate natively; checkbox groups are checked in JS and on the server
                    $input_required = $is_radio ? $required : '';
                    echo '<label class="survey-choice"><input type="' . esc_attr( $question['type'] ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $value ) . '"' . $input_required . $disabled . '> <span>' . esc_html( $choice ) . '</span></label>';
                }
                if ( '' !== $question['other'] ) {
                    echo '<input type="text" name="' . esc_attr( $name . '[other]' ) . '" class="form-input survey-other" maxlength="500" aria-label="' . esc_attr( $question['other'] ) . '" disabled hidden>';
                }
                echo '</fieldset>';
                break;

            case 'letters':
                maraton_survey_render_letters( $question, $label, $name );
                break;
        }

        echo '</div>';
    }

    echo '</div>';
    return ob_get_clean();
}

function maraton_survey_render_letters( $question, $label, $name ) {
    $rows = maraton_survey_letter_rows( $question );

    echo '<p class="form-label">' . esc_html( $label ) . '</p>';

    if ( ! $rows ) {
        echo '<p>' . esc_html__( 'Brak bohaterów_bohaterek do wyboru.', 'amnestympl' ) . '</p>';
        return;
    }

    echo '<div class="survey-letters-scroll"><table class="survey-letters-table">';
    echo '<thead><tr><th scope="col">' . esc_html__( 'Akcja', 'amnestympl' ) . '</th><th scope="col">' . esc_html__( 'Liczba listów do władz', 'amnestympl' ) . '</th><th scope="col">' . esc_html__( 'Liczba listów solidarnościowych', 'amnestympl' ) . '</th><th scope="col">' . esc_html__( 'Łącznie', 'amnestympl' ) . '</th></tr></thead><tbody>';

    foreach ( $rows as $row ) {
        $base = $name . '[rows][' . $row['id'] . ']';
        echo '<tr>';
        echo '<th scope="row">' . esc_html( $row['label'] ) . '</th>';
        echo '<td><input type="number" name="' . esc_attr( $base . '[authorities]' ) . '" class="form-input" min="0" max="' . MARATON_SURVEY_MAX_NUMBER . '" step="1" inputmode="numeric" data-letters="authorities" aria-label="' . esc_attr( $row['label'] . ': ' . __( 'listy do władz', 'amnestympl' ) ) . '"></td>';
        echo '<td><input type="number" name="' . esc_attr( $base . '[solidarity]' ) . '" class="form-input" min="0" max="' . MARATON_SURVEY_MAX_NUMBER . '" step="1" inputmode="numeric" data-letters="solidarity" aria-label="' . esc_attr( $row['label'] . ': ' . __( 'listy solidarnościowe', 'amnestympl' ) ) . '"></td>';
        echo '<td class="survey-letters-total" data-letters-row-total>0</td>';
        echo '</tr>';
    }

    echo '</tbody><tfoot><tr><th scope="row">' . esc_html__( 'Łącznie', 'amnestympl' ) . '</th><td data-letters-total="authorities">0</td><td data-letters-total="solidarity">0</td><td data-letters-total="all">0</td></tr></tfoot>';
    echo '</table></div>';
}

add_filter( 'do_shortcode_tag', 'maraton_survey_enqueue_front', 10, 2 );

function maraton_survey_enqueue_front( $output, $tag ) {
    if ( 'my_events' === $tag && is_user_logged_in() ) {
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        wp_enqueue_style( 'maraton-report-survey', $uri . '/assets/css/report-survey.css', array(), filemtime( $dir . '/assets/css/report-survey.css' ) );
        wp_enqueue_script( 'maraton-report-survey', $uri . '/assets/js/report-survey.js', array(), filemtime( $dir . '/assets/js/report-survey.js' ), true );
    }
    return $output;
}

// 3. SAVING ANSWERS

function maraton_survey_condition_met( $condition, $answers ) {
    if ( ! $condition ) {
        return true;
    }
    $answer = $answers[ $condition['question'] ] ?? null;

    return is_array( $answer ) && in_array( $condition['value'], (array) ( $answer['selected'] ?? array() ), true );
}

/**
 * Reads the answers from the report form. Returns the record stored on the event,
 * or WP_Error with a message for the organiser.
 */
function maraton_survey_collect( $event_id, $input ) {
    $survey  = maraton_survey_get();
    $year    = maraton_survey_event_year( $event_id );
    $input   = is_array( $input ) ? wp_unslash( $input ) : array();
    $answers = array();
    $shown   = array();

    foreach ( $survey['questions'] as $question ) {
        if ( 'heading' === $question['type'] ) {
            continue;
        }
        if ( maraton_survey_is_choice( $question ) && ! $question['options'] && '' === $question['other'] ) {
            continue;
        }
        if ( ! maraton_survey_condition_met( $question['condition'], $answers ) ) {
            continue;
        }

        $id    = $question['id'];
        $label = maraton_survey_label( $question, $year );
        $raw   = $input[ $id ] ?? null;
        $empty = false;

        switch ( $question['type'] ) {
            case 'text':
                $value = mb_substr( sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' ), 0, 500 );
                $empty = '' === $value;
                break;

            case 'textarea':
                $value = mb_substr( sanitize_textarea_field( is_scalar( $raw ) ? (string) $raw : '' ), 0, 5000 );
                $empty = '' === $value;
                break;

            case 'number':
                $value = is_scalar( $raw ) ? trim( (string) $raw ) : '';
                if ( '' !== $value && ( ! ctype_digit( $value ) || (int) $value > MARATON_SURVEY_MAX_NUMBER ) ) {
                    /* translators: %s: question text */
                    return new WP_Error( 'survey_number', sprintf( __( 'Wpisz liczbę całkowitą od 0 w pytaniu: „%s”.', 'amnestympl' ), $label ) );
                }
                $value = '' === $value ? '' : (int) $value;
                $empty = '' === $value;
                break;

            case 'radio':
            case 'checkbox':
                $picked   = is_array( $raw ) ? (array) ( $raw['selected'] ?? array() ) : array();
                $selected = array();
                $other    = '';
                foreach ( $picked as $choice ) {
                    $choice = is_scalar( $choice ) ? (string) $choice : '';
                    if ( MARATON_SURVEY_OTHER === $choice && '' !== $question['other'] ) {
                        $other = mb_substr( sanitize_text_field( is_scalar( $raw['other'] ?? null ) ? (string) $raw['other'] : '' ), 0, 500 );
                        if ( '' === $other ) {
                            /* translators: 1: "other" option, 2: question text */
                            return new WP_Error( 'survey_other', sprintf( __( 'Uzupełnij pole „%1$s” w pytaniu: „%2$s”.', 'amnestympl' ), $question['other'], $label ) );
                        }
                        $selected[] = $question['other'];
                    } elseif ( in_array( $choice, $question['options'], true ) && ! in_array( $choice, $selected, true ) ) {
                        $selected[] = $choice;
                    }
                }
                if ( 'radio' === $question['type'] ) {
                    $selected = array_slice( $selected, 0, 1 );
                    if ( ! in_array( $question['other'], $selected, true ) ) {
                        $other = '';
                    }
                }
                $value = array( 'selected' => $selected, 'other' => $other );
                $empty = ! $selected;
                break;

            case 'letters':
                $rows  = array();
                $total = array( 'authorities' => 0, 'solidarity' => 0 );
                foreach ( maraton_survey_letter_rows( $question ) as $row ) {
                    $posted = is_array( $raw ) && isset( $raw['rows'][ $row['id'] ] ) && is_array( $raw['rows'][ $row['id'] ] ) ? $raw['rows'][ $row['id'] ] : array();
                    $counts = array();
                    foreach ( array( 'authorities', 'solidarity' ) as $kind ) {
                        $number = is_scalar( $posted[ $kind ] ?? null ) ? trim( (string) $posted[ $kind ] ) : '';
                        if ( '' !== $number && ( ! ctype_digit( $number ) || (int) $number > MARATON_SURVEY_MAX_NUMBER ) ) {
                            /* translators: %s: hero name */
                            return new WP_Error( 'survey_letters', sprintf( __( 'Wpisz liczbę listów jako liczbę całkowitą od 0 (%s).', 'amnestympl' ), $row['label'] ) );
                        }
                        $counts[ $kind ]  = (int) $number;
                        $total[ $kind ] += (int) $number;
                    }
                    $rows[] = array(
                        'petition_id' => $row['id'],
                        'label'       => $row['label'],
                        'authorities' => $counts['authorities'],
                        'solidarity'  => $counts['solidarity'],
                    );
                }
                $value = array(
                    'rows'        => $rows,
                    'authorities' => $total['authorities'],
                    'solidarity'  => $total['solidarity'],
                    'total'       => $total['authorities'] + $total['solidarity'],
                );
                break;

            default:
                continue 2;
        }

        if ( $question['required'] && $empty ) {
            /* translators: %s: question text */
            return new WP_Error( 'survey_required', sprintf( __( 'Odpowiedz na pytanie: „%s”.', 'amnestympl' ), $label ) );
        }

        $answers[ $id ] = $value;
        $shown[]        = array(
            'id'      => $id,
            'type'    => $question['type'],
            'label'   => $label,
            'options' => $question['options'],
            'other'   => $question['other'],
        );
    }

    return array(
        'survey_version' => (int) $survey['version'],
        'submitted_at'   => current_time( 'mysql' ),
        'questions'      => $shown,
        'answers'        => $answers,
    );
}

function maraton_survey_letters_answer( $record ) {
    foreach ( $record['questions'] as $question ) {
        if ( 'letters' === $question['type'] ) {
            return $record['answers'][ $question['id'] ] ?? null;
        }
    }
    return null;
}

function maraton_survey_format_answer( $type, $value ) {
    if ( 'letters' === $type ) {
        return is_array( $value ) ? (string) $value['total'] : '';
    }
    if ( is_array( $value ) ) {
        $selected = (array) ( $value['selected'] ?? array() );
        if ( '' !== (string) ( $value['other'] ?? '' ) && $selected ) {
            $last              = count( $selected ) - 1;
            $selected[ $last ] = $selected[ $last ] . ': ' . $value['other'];
        }
        return implode( ', ', $selected );
    }
    return (string) $value;
}

// 4. ADMIN: ANSWERS IN THE EVENT EDITOR

function maraton_survey_render_admin_answers( $event_id ) {
    $record = get_post_meta( $event_id, MARATON_SURVEY_META, true );
    if ( ! is_array( $record ) || empty( $record['questions'] ) ) {
        return;
    }

    echo '<h3>' . esc_html__( 'Raport i ankieta', 'amnestympl' ) . '</h3>';
    echo '<table class="widefat striped maraton-survey-answers"><tbody>';

    foreach ( $record['questions'] as $question ) {
        $value = $record['answers'][ $question['id'] ] ?? '';
        echo '<tr><th scope="row">' . esc_html( $question['label'] ) . '</th><td>';

        if ( 'letters' === $question['type'] && is_array( $value ) ) {
            echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Akcja', 'amnestympl' ) . '</th><th>' . esc_html__( 'Do władz', 'amnestympl' ) . '</th><th>' . esc_html__( 'Solidarnościowe', 'amnestympl' ) . '</th><th>' . esc_html__( 'Łącznie', 'amnestympl' ) . '</th></tr></thead><tbody>';
            foreach ( $value['rows'] as $row ) {
                echo '<tr><td>' . esc_html( $row['label'] ) . '</td><td>' . (int) $row['authorities'] . '</td><td>' . (int) $row['solidarity'] . '</td><td>' . (int) ( $row['authorities'] + $row['solidarity'] ) . '</td></tr>';
            }
            echo '<tr><th>' . esc_html__( 'Łącznie', 'amnestympl' ) . '</th><th>' . (int) $value['authorities'] . '</th><th>' . (int) $value['solidarity'] . '</th><th>' . (int) $value['total'] . '</th></tr>';
            echo '</tbody></table>';
        } else {
            $text = maraton_survey_format_answer( $question['type'], $value );
            echo '' !== $text ? nl2br( esc_html( $text ) ) : '&ndash;';
        }

        echo '</td></tr>';
    }

    echo '</tbody></table>';
    echo '<p class="description">' . esc_html( sprintf( __( 'Wysłano: %s', 'amnestympl' ), $record['submitted_at'] ) ) . '</p>';
}

// 5. ADMIN: SURVEY BUILDER (Wydarzenia → Ankieta raportu)

add_action( 'admin_menu', 'maraton_survey_admin_menu' );

function maraton_survey_admin_menu() {
    $hook = add_submenu_page(
        'edit.php?post_type=wydarzenie',
        __( 'Ankieta raportu', 'amnestympl' ),
        __( 'Ankieta raportu', 'amnestympl' ),
        'manage_options',
        'maraton_report_survey',
        'maraton_survey_admin_page'
    );

    add_action( "load-{$hook}", static function () {
        add_action( 'admin_enqueue_scripts', 'maraton_survey_admin_assets' );
    } );
}

function maraton_survey_admin_assets() {
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    wp_enqueue_style( 'maraton-report-survey-admin', $uri . '/assets/css/report-survey-admin.css', array(), filemtime( $dir . '/assets/css/report-survey-admin.css' ) );
    wp_enqueue_script( 'maraton-report-survey-admin', $uri . '/assets/js/report-survey-admin.js', array(), filemtime( $dir . '/assets/js/report-survey-admin.js' ), true );
}

function maraton_survey_admin_petitions() {
    $petitions = get_posts( array(
        'post_type'      => 'amnesty_petition',
        'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
        'posts_per_page' => -1,
        'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
    ) );

    return array_map( static function ( $petition ) {
        return array(
            'id'        => $petition->ID,
            'label'     => maraton_survey_petition_label( $petition ),
            'published' => 'publish' === $petition->post_status,
        );
    }, $petitions );
}

function maraton_survey_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $survey  = maraton_survey_get();
    $notices = get_transient( 'maraton_survey_notices_' . get_current_user_id() );
    delete_transient( 'maraton_survey_notices_' . get_current_user_id() );
    ?>
    <div class="wrap maraton-survey-admin">
        <h1><?php esc_html_e( 'Ankieta raportu', 'amnestympl' ); ?></h1>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ankieta zapisana. Nowe raporty będą miały te pytania.', 'amnestympl' ); ?></p></div>
        <?php endif; ?>
        <?php if ( isset( $_GET['invalid'] ) ) : ?>
            <div class="notice notice-error"><p><?php esc_html_e( 'Nie udało się odczytać ankiety, nic nie zostało zmienione. Odśwież stronę i spróbuj ponownie.', 'amnestympl' ); ?></p></div>
        <?php endif; ?>
        <?php foreach ( (array) $notices as $notice ) : ?>
            <div class="notice notice-warning"><p><?php echo esc_html( $notice ); ?></p></div>
        <?php endforeach; ?>

        <div class="maraton-survey-help">
            <p><?php esc_html_e( 'Pytania pokazują się organizatorom w formularzu raportu w tej kolejności. Zmiany obowiązują w raportach wysłanych po zapisaniu; raporty już wysłane zachowują pytania i odpowiedzi z chwili wysłania.', 'amnestympl' ); ?></p>
            <p><?php esc_html_e( 'Usunięte pytanie znika z formularza, ale odpowiedzi na nie zostają w raportach i w eksporcie CSV. W treści pytania {rok} zamienia się na rok wydarzenia.', 'amnestympl' ); ?></p>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="maraton-survey-form">
            <input type="hidden" name="action" value="maraton_save_report_survey">
            <?php wp_nonce_field( 'maraton_save_report_survey' ); ?>
            <input type="hidden" name="survey_json" id="maraton-survey-json" value="">

            <div id="maraton-survey-builder"
                data-survey="<?php echo esc_attr( wp_json_encode( $survey['questions'] ) ); ?>"
                data-types="<?php echo esc_attr( wp_json_encode( maraton_survey_types() ) ); ?>"
                data-petitions="<?php echo esc_attr( wp_json_encode( maraton_survey_admin_petitions() ) ); ?>">
                <noscript><p><?php esc_html_e( 'Edycja ankiety wymaga włączonego JavaScriptu.', 'amnestympl' ); ?></p></noscript>
            </div>

            <p class="submit"><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Zapisz ankietę', 'amnestympl' ); ?></button></p>
        </form>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="maraton-survey-reset" onsubmit="return confirm('<?php echo esc_js( __( 'Przywrócić ankietę początkową? Twoje zmiany pytań zostaną zastąpione. Wysłane raporty się nie zmienią.', 'amnestympl' ) ); ?>');">
            <input type="hidden" name="action" value="maraton_reset_report_survey">
            <?php wp_nonce_field( 'maraton_reset_report_survey' ); ?>
            <button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Przywróć ankietę początkową', 'amnestympl' ); ?></button>
        </form>
    </div>
    <?php
}

function maraton_survey_store( $raw ) {
    list( $survey, $notices ) = maraton_survey_sanitize( $raw, maraton_survey_get() );
    update_option( MARATON_SURVEY_OPTION, $survey, false );

    if ( $notices ) {
        set_transient( 'maraton_survey_notices_' . get_current_user_id(), $notices, 5 * MINUTE_IN_SECONDS );
    }
    return $survey;
}

add_action( 'admin_post_maraton_save_report_survey', 'maraton_survey_handle_save' );

function maraton_survey_handle_save() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Brak uprawnień.', 'amnestympl' ), 403 );
    }
    check_admin_referer( 'maraton_save_report_survey' );

    $page = admin_url( 'edit.php?post_type=wydarzenie&page=maraton_report_survey' );
    $raw  = json_decode( wp_unslash( (string) ( $_POST['survey_json'] ?? '' ) ), true );

    if ( ! is_array( $raw ) || ! $raw ) {
        wp_safe_redirect( add_query_arg( 'invalid', '1', $page ) );
        exit;
    }

    maraton_survey_store( $raw );
    wp_safe_redirect( add_query_arg( 'saved', '1', $page ) );
    exit;
}

add_action( 'admin_post_maraton_reset_report_survey', 'maraton_survey_handle_reset' );

function maraton_survey_handle_reset() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Brak uprawnień.', 'amnestympl' ), 403 );
    }
    check_admin_referer( 'maraton_reset_report_survey' );

    maraton_survey_store( maraton_survey_default()['questions'] );
    wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'edit.php?post_type=wydarzenie&page=maraton_report_survey' ) ) );
    exit;
}

// 6. ADMIN: CSV EXPORT OF REPORTS

add_action( 'manage_posts_extra_tablenav', 'maraton_survey_export_button' );

function maraton_survey_export_button( $which ) {
    if ( 'top' !== $which || 'wydarzenie' !== get_current_screen()->post_type || ! current_user_can( 'edit_posts' ) ) {
        return;
    }
    $url = wp_nonce_url( admin_url( 'admin-post.php?action=maraton_export_reports' ), 'maraton_export_reports' );
    echo '<div class="alignleft actions"><a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Eksportuj raporty do CSV', 'amnestympl' ) . '</a></div>';
}

add_action( 'admin_post_maraton_export_reports', 'maraton_survey_export_csv' );

function maraton_survey_export_csv() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'Brak uprawnień.', 'amnestympl' ), 403 );
    }
    check_admin_referer( 'maraton_export_reports' );

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="raporty-' . wp_date( 'Y-m-d' ) . '.csv"' );

    $out = fopen( 'php://output', 'w' );
    // BOM + semicolon so Polish Excel opens it with correct encoding and columns
    fwrite( $out, "\xEF\xBB\xBF" );
    foreach ( maraton_survey_export_rows() as $row ) {
        fputcsv( $out, $row, ';' );
    }
    fclose( $out );
    exit;
}

/**
 * Header and one row per submitted report. Columns follow the current survey; questions and
 * heroes that only exist in older reports are appended at the end.
 */
function maraton_survey_export_rows() {
    $events = get_posts( array(
        'post_type'      => 'wydarzenie',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_key'       => 'raport_zlozony',
        'meta_value'     => '1',
        'orderby'        => 'date',
        'order'          => 'ASC',
    ) );

    $survey  = maraton_survey_get();
    $records = array();
    $columns = array();
    $heroes  = array();

    foreach ( $survey['questions'] as $question ) {
        if ( ! in_array( $question['type'], array( 'heading', 'letters' ), true ) ) {
            // Reports from different years share a column, so the year placeholder is dropped
            $columns[ $question['id'] ] = preg_replace( '/\s*\{rok\}/', '', $question['label'] );
        }
    }
    foreach ( $events as $event ) {
        $record = get_post_meta( $event->ID, MARATON_SURVEY_META, true );
        $records[ $event->ID ] = is_array( $record ) ? $record : null;
        if ( ! $records[ $event->ID ] ) {
            continue;
        }
        foreach ( $record['questions'] as $question ) {
            if ( 'letters' === $question['type'] ) {
                foreach ( (array) ( $record['answers'][ $question['id'] ]['rows'] ?? array() ) as $row ) {
                    $heroes[ $row['petition_id'] ] = $heroes[ $row['petition_id'] ] ?? $row['label'];
                }
            } elseif ( ! isset( $columns[ $question['id'] ] ) ) {
                $columns[ $question['id'] ] = $survey['archived'][ $question['id'] ]['label'] ?? $question['label'];
            }
        }
    }

    $header = array( 'ID wydarzenia', 'Wydarzenie', 'Organizator', 'E-mail organizatora', 'Miejscowość', 'Data wydarzenia', 'Data raportu' );
    foreach ( $heroes as $hero ) {
        $header[] = $hero . ': do władz';
        $header[] = $hero . ': solidarnościowe';
    }
    $header = array_merge( $header, array( 'Listy do władz łącznie', 'Listy solidarnościowe łącznie', 'Listy łącznie' ), array_values( $columns ) );
    $header = array_merge( $header, array( 'Podsumowanie', 'Materiały pobrane', 'Uwagi o materiałach', 'Link do zdjęć', 'Plik ZIP' ) );

    $rows = array( array_map( 'maraton_survey_csv_safe', $header ) );

    foreach ( $events as $event ) {
        $record  = $records[ $event->ID ];
        $answers = $record ? $record['answers'] : array();
        $types   = array();
        foreach ( $record ? $record['questions'] : array() as $question ) {
            $types[ $question['id'] ] = $question['type'];
        }
        $letters = $record ? maraton_survey_letters_answer( $record ) : null;
        $by_hero = array();
        foreach ( (array) ( $letters['rows'] ?? array() ) as $row ) {
            $by_hero[ $row['petition_id'] ] = $row;
        }

        $line = array(
            $event->ID,
            $event->post_title,
            get_the_author_meta( 'display_name', $event->post_author ),
            get_the_author_meta( 'user_email', $event->post_author ),
            function_exists( 'get_field' ) ? get_field( 'miejscowosc', $event->ID ) : get_post_meta( $event->ID, 'miejscowosc', true ),
            function_exists( 'maraton_get_event_datetime' ) ? maraton_get_event_datetime( $event->ID, 'start' ) : '',
            $record ? $record['submitted_at'] : '',
        );
        foreach ( array_keys( $heroes ) as $petition_id ) {
            $line[] = isset( $by_hero[ $petition_id ] ) ? $by_hero[ $petition_id ]['authorities'] : '';
            $line[] = isset( $by_hero[ $petition_id ] ) ? $by_hero[ $petition_id ]['solidarity'] : '';
        }
        // Reports sent before the survey only have the old single total
        $line[] = $letters ? $letters['authorities'] : '';
        $line[] = $letters ? $letters['solidarity'] : '';
        $line[] = $letters ? $letters['total'] : get_post_meta( $event->ID, 'liczba_podpisanych_listow', true );

        foreach ( array_keys( $columns ) as $id ) {
            $line[] = isset( $answers[ $id ] ) ? maraton_survey_format_answer( $types[ $id ] ?? '', $answers[ $id ] ) : '';
        }

        $materials = array( 'yes' => 'Tak', 'no' => 'Nie', 'unknown' => 'Nie wiem / nie dotyczy' );
        $line[]    = get_post_meta( $event->ID, 'tresc_raportu', true );
        $line[]    = $materials[ get_post_meta( $event->ID, 'materialy_pobrane', true ) ] ?? '';
        $line[]    = get_post_meta( $event->ID, 'materialy_uwagi', true );
        $line[]    = get_post_meta( $event->ID, 'link_do_zdjec', true );
        $line[]    = get_post_meta( $event->ID, 'plik_zip_url', true );

        $rows[] = array_map( 'maraton_survey_csv_safe', $line );
    }

    return $rows;
}

// Values starting with these characters would be run as formulas by Excel
function maraton_survey_csv_safe( $value ) {
    $value = (string) $value;
    return ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) ? "'" . $value : $value;
}
