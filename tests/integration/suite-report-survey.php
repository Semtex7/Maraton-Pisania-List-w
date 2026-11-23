<?php
// Report survey: builder sanitising, panel form, answers, CSV export.
// Included from run.php (uses t(), begin(), rollback(), $admins, Test_Json, Test_Redirect).

echo "\n== 21. Raport: ankieta ==\n";

if ( ! function_exists( 'maraton_survey_get' ) ) {
    skip( 'sekcja 21', 'moduł ankiety niezaładowany' );
    return;
}
if ( ! class_exists( 'Test_Die' ) ) {
    class Test_Die extends Exception {}
}
$survey_die = function () { return function ( $msg ) { throw new Test_Die( is_string( $msg ) ? $msg : '' ); }; };

function survey_test_ids( $survey ) {
    return array_map( function ( $q ) { return $q['id']; }, $survey['questions'] );
}

function survey_test_event( $author ) {
    return wp_insert_post( array(
        'post_type'   => 'wydarzenie',
        'post_status' => 'publish',
        'post_title'  => 'Test ankiety raportu',
        'post_author' => $author,
        'meta_input'  => array( 'data_czas_zakonczenia' => '2025-12-10 18:00:00' ),
    ) );
}

// A complete set of answers for the default survey
function survey_test_answers( $rows ) {
    $letters = array();
    foreach ( $rows as $i => $row ) {
        $letters[ $row['id'] ] = array( 'authorities' => (string) ( 10 + $i ), 'solidarity' => (string) ( 1 + $i ) );
    }
    return array(
        'q_letters'           => array( 'rows' => $letters ),
        'q_participants'      => '25',
        'q_education'         => array( 'selected' => 'Nie' ),
        'q_education_count'   => '99',
        'q_school'            => array( 'selected' => 'Szkoła podstawowa' ),
        'q_edition'           => '2',
        'q_first_here'        => array( 'selected' => 'Nie' ),
        'q_source'            => array( 'selected' => array( 'z newslettera Amnesty', 'z mediów społecznościowych (Internet)' ) ),
        'q_source_web'        => array( 'selected' => array( 'Instagram' ) ),
        'q_rate_heroes'       => array( 'selected' => 'Dobrze' ),
        'q_rate_office'       => array( 'selected' => 'Bardzo dobrze' ),
        'q_rate_website'      => array( 'selected' => 'Przeciętnie' ),
        'q_rate_materials'    => array( 'selected' => 'Dobrze' ),
        'q_promotion'         => array( 'selected' => array( 'Radio', MARATON_SURVEY_OTHER ), 'other' => 'Gazetka szkolna' ),
        'q_helpful_materials' => array( 'selected' => array( 'Wzory listów' ) ),
        'q_funding'           => array( 'selected' => array( 'Środki prywatne' ) ),
        'q_engagement'        => array( 'selected' => array( 'Listy tradycyjne' ) ),
        'q_next_edition'      => array( 'selected' => 'Tak' ),
        'q_next_edition_why'  => 'ukryte pytanie',
        'q_discord'           => array( 'selected' => 'Nie' ),
        'q_discord_opinion'   => 'Nie korzystam z Discorda.',
        'q_comments'          => '=HYPERLINK("x")',
    );
}

// --- Definition and builder sanitising ---
begin();
delete_option( MARATON_SURVEY_OPTION );
$default = maraton_survey_get();
$ids     = survey_test_ids( $default );
t( 'ankieta domyślna bez zapisanej opcji', 0 === $default['version'] && count( $ids ) > 20 );
t( 'ankieta domyślna: unikalne id i jedna tabela listów', count( $ids ) === count( array_unique( $ids ) ) && 1 === count( array_filter( $default['questions'], function ( $q ) { return 'letters' === $q['type']; } ) ) );

$raw = $default['questions'];
// Remove the letters table and q_school, add an empty card, an invalid type and a broken id
$raw = array_values( array_filter( $raw, function ( $q ) { return ! in_array( $q['id'], array( 'q_letters', 'q_school' ), true ); } ) );
$raw[] = array( 'id' => 'q_new1', 'type' => 'text', 'label' => '' );
$raw[] = array( 'id' => 'q_bad', 'type' => 'script', 'label' => 'X' );
$raw[] = array( 'id' => 'ZLE ID', 'type' => 'checkbox', 'label' => 'Nowe <b>pytanie</b>', 'options' => array( 'A', 'A', ' B ', '' ), 'required' => true, 'condition' => array( 'question' => 'q_discord', 'value' => 'Tak' ) );
$raw[] = array( 'id' => 'q_cond_later', 'type' => 'text', 'label' => 'Warunek w dół', 'condition' => array( 'question' => 'q_after', 'value' => 'Tak' ) );
$raw[] = array( 'id' => 'q_after', 'type' => 'radio', 'label' => 'Później', 'options' => array( 'Tak' ) );
$raw[] = array( 'id' => 'q_heading_req', 'type' => 'heading', 'label' => 'Sekcja', 'required' => true, 'condition' => array( 'question' => 'q_discord', 'value' => 'Tak' ) );
$raw[] = array( 'id' => 'q_dup', 'type' => 'text', 'label' => 'Pierwszy' );
$raw[] = array( 'id' => 'q_dup', 'type' => 'text', 'label' => 'Drugi' );

list( $saved, $notices ) = maraton_survey_sanitize( $raw, $default );
$by_label = array();
foreach ( $saved['questions'] as $q ) { $by_label[ $q['label'] ] = $q; }
$new = $by_label['Nowe pytanie'] ?? null;

t( 'zapis: wersja +1', 1 === $saved['version'] );
t( 'zapis: pusta karta i nieznany typ pominięte', ! in_array( 'q_new1', survey_test_ids( $saved ), true ) && ! isset( $by_label['X'] ) );
t( 'zapis: usunięta tabela listów wraca na koniec', 'letters' === end( $saved['questions'] )['type'] && 'q_letters' === end( $saved['questions'] )['id'] );
t( 'zapis: usunięte pytanie w archiwum', isset( $saved['archived']['q_school'] ) && ! in_array( 'q_school', survey_test_ids( $saved ), true ) );
t( 'zapis: HTML z treści usunięty, nowe id nadane', $new && preg_match( '/^q_[a-z0-9]{8}$/', $new['id'] ) );
t( 'zapis: odpowiedzi bez duplikatów i pustych', $new && array( 'A', 'B' ) === $new['options'] );
t( 'zapis: poprawny warunek zachowany', $new && array( 'question' => 'q_discord', 'value' => 'Tak' ) === $new['condition'] );
t( 'zapis: warunek do pytania niżej usunięty z komunikatem', null === $by_label['Warunek w dół']['condition'] && (bool) array_filter( $notices, function ( $n ) { return false !== strpos( $n, 'Warunek w dół' ); } ) );
t( 'zapis: nagłówek bez wymagalności i warunku', false === $by_label['Sekcja']['required'] && null === $by_label['Sekcja']['condition'] );
t( 'zapis: powtórzone id dostaje nowe', $by_label['Pierwszy']['id'] !== $by_label['Drugi']['id'] );

// Restoring a question takes it out of the archive
$back = $saved['questions'];
$back[] = maraton_survey_find( $default['questions'], 'q_school' );
list( $saved2 ) = maraton_survey_sanitize( $back, $saved );
t( 'zapis: przywrócone pytanie znika z archiwum', ! isset( $saved2['archived']['q_school'] ) && 2 === $saved2['version'] );

$warn = maraton_survey_sanitize( array( array( 'id' => 'q_empty', 'type' => 'radio', 'label' => 'Bez odpowiedzi' ) ), $default );
t( 'zapis: pytanie wyboru bez odpowiedzi daje komunikat', (bool) $warn[1] );
rollback();

// --- Builder page handlers ---
begin();
delete_option( MARATON_SURVEY_OPTION );
$sub_id = wp_insert_user( array( 'user_login' => 'survey_test_sub', 'user_pass' => wp_generate_password(), 'user_email' => 'survey_test_sub@example.invalid', 'role' => 'subscriber' ) );
add_filter( 'wp_die_handler', $survey_die );
wp_set_current_user( $sub_id );
$_POST = $_REQUEST = array( '_wpnonce' => wp_create_nonce( 'maraton_save_report_survey' ), 'survey_json' => wp_json_encode( array() ) );
$died = false;
try { maraton_survey_handle_save(); } catch ( Test_Die $e ) { $died = true; } catch ( Test_Redirect $e ) {}
t( 'edytor: subskrybent nie zapisze ankiety', $died && false === get_option( MARATON_SURVEY_OPTION ) );

wp_set_current_user( $admins[0] );
$questions   = $default['questions'];
$questions[] = array( 'id' => 'q_extra', 'type' => 'text', 'label' => 'Dodatkowe pytanie' );
$_POST = $_REQUEST = array( '_wpnonce' => wp_create_nonce( 'maraton_save_report_survey' ), 'survey_json' => wp_slash( wp_json_encode( $questions ) ) );
$location = '';
try { maraton_survey_handle_save(); } catch ( Test_Redirect $e ) { $location = $e->getMessage(); }
$stored = get_option( MARATON_SURVEY_OPTION );
t( 'edytor: administrator zapisuje ankietę', is_array( $stored ) && in_array( 'q_extra', survey_test_ids( $stored ), true ) && false !== strpos( $location, 'saved=1' ) );

$_POST = $_REQUEST = array( '_wpnonce' => wp_create_nonce( 'maraton_save_report_survey' ), 'survey_json' => 'nie json' );
$location = '';
try { maraton_survey_handle_save(); } catch ( Test_Redirect $e ) { $location = $e->getMessage(); }
t( 'edytor: błędne dane nie zmieniają ankiety', false !== strpos( $location, 'invalid=1' ) && get_option( MARATON_SURVEY_OPTION ) == $stored );

$_POST = $_REQUEST = array( '_wpnonce' => wp_create_nonce( 'maraton_reset_report_survey' ) );
try { maraton_survey_handle_reset(); } catch ( Test_Redirect $e ) {}
$reset = get_option( MARATON_SURVEY_OPTION );
t( 'edytor: przywrócenie ankiety początkowej', ! in_array( 'q_extra', survey_test_ids( $reset ), true ) && isset( $reset['archived']['q_extra'] ) );

ob_start();
maraton_survey_admin_page();
$page = ob_get_clean();
t( 'edytor: strona z danymi dla skryptu', false !== strpos( $page, 'id="maraton-survey-builder"' ) && false !== strpos( $page, 'data-survey=' ) );
remove_filter( 'wp_die_handler', $survey_die );
$_POST = $_REQUEST = array();
wp_set_current_user( 0 );
rollback();

// --- Panel form, answers and saving ---
$petition_ids = get_posts( array( 'post_type' => 'amnesty_petition', 'post_status' => 'publish', 'posts_per_page' => 2, 'fields' => 'ids' ) );
if ( ! $petition_ids ) {
    skip( 'formularz i zapis raportu', 'brak opublikowanych petycji' );
    return;
}

begin();
delete_option( MARATON_SURVEY_OPTION );
$author   = wp_insert_user( array( 'user_login' => 'survey_test_org', 'user_pass' => wp_generate_password(), 'user_email' => 'survey_test_org@example.invalid', 'role' => 'subscriber' ) );
$event_id = survey_test_event( $author );
$letters  = maraton_survey_find( maraton_survey_get()['questions'], 'q_letters' );
$rows     = maraton_survey_letter_rows( $letters );

$html = maraton_survey_render_fields( $event_id );
t( 'formularz: tabela z wierszem dla każdej petycji', count( $rows ) >= count( $petition_ids ) && substr_count( $html, 'data-letters="authorities"' ) === count( $rows ) );
t( 'formularz: {rok} = rok wydarzenia', false !== strpos( $html, 'Maratonu 2025?' ) && false === strpos( $html, '{rok}' ) );
t( 'formularz: pytanie warunkowe ukryte i wyłączone', (bool) preg_match( '/data-question="q_education_count"[^>]*hidden.*?name="survey\[q_education_count\]"[^>]*disabled/s', $html ) );
t( 'formularz: pole „Inne” ukryte do czasu wyboru', (bool) preg_match( '/name="survey\[q_promotion\]\[other\]"[^>]*disabled hidden/', $html ) );

// Picked petitions with a custom label, in the order of the petitions list
list( $picked_survey ) = maraton_survey_sanitize( array( array( 'id' => 'q_letters', 'type' => 'letters', 'label' => 'Listy', 'petitions' => array( array( 'id' => $petition_ids[0], 'label' => 'Własna nazwa' ), array( 'id' => 999999999 ) ) ) ), maraton_survey_default() );
$picked_rows = maraton_survey_letter_rows( $picked_survey['questions'][0] );
t( 'formularz: wybrane petycje i własna nazwa', 1 === count( $picked_rows ) && 'Własna nazwa' === $picked_rows[0]['label'] );

$answers = survey_test_answers( $rows );
$record  = maraton_survey_collect( $event_id, wp_slash( $answers ) );
t( 'odpowiedzi: komplet przechodzi', is_array( $record ), is_wp_error( $record ) ? $record->get_error_message() : '' );
if ( is_array( $record ) ) {
    $sum = 0;
    foreach ( $rows as $i => $row ) { $sum += 11 + 2 * $i; }
    t( 'odpowiedzi: sumy listów liczone na serwerze', $sum === $record['answers']['q_letters']['total'] && 10 === $record['answers']['q_letters']['rows'][0]['authorities'] );
    t( 'odpowiedzi: ukryte pytania pominięte', ! isset( $record['answers']['q_education_count'] ) && ! isset( $record['answers']['q_next_edition_why'] ) );
    t( 'odpowiedzi: pytanie warunkowe przy spełnionym warunku zapisane', array( 'Instagram' ) === $record['answers']['q_source_web']['selected'] );
    t( 'odpowiedzi: „Inne” z wpisanym tekstem', array( 'Radio', 'Inne (jakie?)' ) === $record['answers']['q_promotion']['selected'] && 'Gazetka szkolna' === $record['answers']['q_promotion']['other'] );
    t( 'odpowiedzi: kopia treści pytań z rokiem', 'Jak oceniasz dobór i przygotowanie opisów bohaterów_bohaterek Maratonu 2025?' === maraton_survey_find( $record['questions'], 'q_rate_heroes' )['label'] );
}

$cases = array(
    'brak wymaganej odpowiedzi'       => array( 'q_participants', '' ),
    'liczba ujemna'                   => array( 'q_participants', '-3' ),
    'liczba z ułamkiem'               => array( 'q_edition', '1.5' ),
    'odpowiedź spoza listy'           => array( 'q_school', array( 'selected' => 'Wymyślona' ) ),
    'pusty wielokrotny wybór'         => array( 'q_funding', array( 'selected' => array() ) ),
    '„Inne” bez tekstu'               => array( 'q_promotion', array( 'selected' => array( MARATON_SURVEY_OTHER ), 'other' => ' ' ) ),
    'ujemna liczba listów'            => array( 'q_letters', array( 'rows' => array( $rows[0]['id'] => array( 'authorities' => '-1' ) ) ) ),
);
foreach ( $cases as $name => $case ) {
    $bad              = $answers;
    $bad[ $case[0] ]  = $case[1];
    t( 'odpowiedzi odrzucone: ' . $name, is_wp_error( maraton_survey_collect( $event_id, wp_slash( $bad ) ) ) );
}
$bad = $answers;
$bad['q_education']       = array( 'selected' => 'Tak' );
$bad['q_education_count'] = '12';
$with_condition           = maraton_survey_collect( $event_id, wp_slash( $bad ) );
t( 'odpowiedzi: liczba z zajęć zapisana, gdy zajęcia były', is_array( $with_condition ) && 12 === $with_condition['answers']['q_education_count'] );

// Full AJAX save by the event author
add_filter( 'wp_doing_ajax', '__return_true' );
wp_set_current_user( $author );
$_POST = $_REQUEST = wp_slash( array(
    'event_id'       => $event_id,
    'report_nonce'   => wp_create_nonce( 'save_report_action' ),
    'report_content' => 'Podsumowanie testowe',
    'survey'         => $answers,
) );
ob_start(); try { ajax_save_event_report(); } catch ( Test_Json $e ) {} $resp = json_decode( ob_get_clean(), true );
$stored = get_post_meta( $event_id, MARATON_SURVEY_META, true );
t( 'zapis raportu: sukces i odpowiedzi przy wydarzeniu', ! empty( $resp['success'] ) && is_array( $stored ) && isset( $stored['answers']['q_school'] ), wp_json_encode( $resp ) );
t( 'zapis raportu: stare pola (listy, uczestnicy) uzupełnione', (string) ( $record['answers']['q_letters']['total'] ?? '' ) === get_post_meta( $event_id, 'liczba_podpisanych_listow', true ) && '25' === get_post_meta( $event_id, 'liczba_uczestnikow', true ) );

$other_event = survey_test_event( $author );
$bad         = $answers;
$bad['q_participants'] = '';
$_POST = $_REQUEST = wp_slash( array( 'event_id' => $other_event, 'report_nonce' => wp_create_nonce( 'save_report_action' ), 'report_content' => 'x', 'survey' => $bad ) );
ob_start(); try { ajax_save_event_report(); } catch ( Test_Json $e ) {} $resp = json_decode( ob_get_clean(), true );
remove_filter( 'wp_doing_ajax', '__return_true' );
t( 'zapis raportu: błąd ankiety nie oznacza raportu jako złożonego', empty( $resp['success'] ) && '' === get_post_meta( $other_event, 'raport_zlozony', true ) );

// Changing the survey later does not change the stored report
wp_set_current_user( $admins[0] );
$changed = maraton_survey_get()['questions'];
foreach ( $changed as &$q ) {
    if ( 'q_school' === $q['id'] ) { $q['label'] = 'Zmieniona treść'; }
}
unset( $q );
$changed = array_values( array_filter( $changed, function ( $q ) { return 'q_discord_opinion' !== $q['id']; } ) );
maraton_survey_store( $changed );
$stored_after = get_post_meta( $event_id, MARATON_SURVEY_META, true );
t( 'zmiana ankiety nie zmienia wysłanego raportu', $stored_after == $stored && 'Do jakiej szkoły uczęszczasz/w jakiej szkole nauczasz?' === maraton_survey_find( $stored_after['questions'], 'q_school' )['label'] );

ob_start(); render_event_report_meta_box( get_post( $event_id ) ); $box = ob_get_clean();
t( 'metabox: odpowiedzi i tabela listów', false !== strpos( $box, 'Gazetka szkolna' ) && false !== strpos( $box, esc_html( $rows[0]['label'] ) ) );

// CSV: hero columns, archived question keeps its column, formula protection
$csv    = maraton_survey_export_rows();
$header = $csv[0];
$line   = null;
foreach ( $csv as $row ) { if ( (string) $event_id === (string) $row[0] ) { $line = array_combine( $header, $row ); } }
t( 'CSV: wiersz raportu', null !== $line );
if ( $line ) {
    t( 'CSV: kolumny bohaterów i sumy', '10' === (string) $line[ $rows[0]['label'] . ': do władz' ] && (string) $record['answers']['q_letters']['total'] === (string) $line['Listy łącznie'] );
    t( 'CSV: aktualna treść pytania w nagłówku', 'Szkoła podstawowa' === $line['Zmieniona treść'] );
    t( 'CSV: usunięte pytanie ma kolumnę', 'Nie korzystam z Discorda.' === ( $line['Jeśli tak – co sądzisz o czacie? Jeśli nie – dlaczego?'] ?? null ) );
    t( 'CSV: „Inne” z tekstem', 'Radio, Inne (jakie?): Gazetka szkolna' === $line['Jakich kanałów/narzędzi używałeś_aś do promocji Maratonu?'] );
    t( 'CSV: ochrona przed formułami', "'=HYPERLINK(\"x\")" === $line['Czy masz dla nas jakieś uwagi/komentarze?'] );
}

add_filter( 'wp_die_handler', $survey_die );
wp_set_current_user( $author );
$_GET = $_REQUEST = array( '_wpnonce' => wp_create_nonce( 'maraton_export_reports' ) );
$died = false;
ob_start(); try { maraton_survey_export_csv(); } catch ( Test_Die $e ) { $died = true; } ob_end_clean();
t( 'CSV: organizator nie pobierze eksportu', $died );
remove_filter( 'wp_die_handler', $survey_die );

$_POST = $_GET = $_REQUEST = array();
wp_set_current_user( 0 );
rollback();
