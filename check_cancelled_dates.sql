SELECT 
    booking_id,
    booking_status,
    booking_placed_date,
    booking_cancelled_date,
    last_updated,
    TIMESTAMPDIFF(MINUTE, booking_cancelled_date, NOW()) as minutes_since_cancelled
FROM wp_newbook_cache 
WHERE booking_id IN (33145, 33139)
OR booking_status = 'cancelled'
ORDER BY booking_cancelled_date DESC
LIMIT 10;
