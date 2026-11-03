# How-to: Connect Excel and Power BI to a ULAMS Saved-Report OData v4 Feed

## Connecting Excel to the OData v4 Feed

1. Open Excel and go to the "Data" tab.
2. Click on "Get Data" and then select "From Other Sources" > "From OData Feed".
3. In the "From OData Feed" dialog box, enter the feed URL: `https://<tenant-host>/TODO(verify-path)`.
4. Click "OK" to proceed.
5. In the "Navigator" window, select the saved-report entity you want to connect to.
6. Click "Load" to load the data into Excel.

## Connecting Power BI to the OData v4 Feed

1. Open Power BI Desktop.
2. Click on "Home" and then select "Get Data" > "More" > "OData Feed".
3. In the "OData Feed" dialog box, enter the feed URL: `https://<tenant-host>/TODO(verify-path)`.
4. Click "OK" to proceed.
5. In the "Navigator" window, select the saved-report entity you want to connect to.
6. Click "Load" to load the data into Power BI.

## Troubleshooting

1. **401 Unauthorized Error**: Ensure that the token used has the `reports:read` scope and is valid. Re-authenticate if necessary.
2. **Empty Feed**: Verify that the feed URL is correct and that there are saved reports available in the ULAMS instance.
3. **Date Formats**: Ensure that the date formats in the feed are compatible with Excel or Power BI. You may need to format the dates in Power Query.
4. **Large Reports**: For large reports, consider loading only the necessary columns or using Power Query to filter the data before loading.
5. **Refresh Issues**: If you encounter issues refreshing the data, ensure that the connection settings are correct and that the token has not expired. You may need to re-authenticate and update the connection.
