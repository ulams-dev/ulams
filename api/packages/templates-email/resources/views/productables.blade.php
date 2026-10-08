<table>
  <thead>
    <tr>
      <th>#</th>
      <th>{{ __('Content') }}</th>
    </tr>
  </thead>
  <tbody>
    @foreach($productables as $productable)
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $productable['name'] }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
